# Sales CSV Import Architecture Flow

Dokumen ini menjelaskan arsitektur dan alur aktual endpoint import CSV sales. Fokusnya adalah apa yang terjadi saat client mengunggah file, mulai dari route, controller, request validation, parser CSV, pengelompokan data, command bus, application handler, domain, persistence, hingga JSON response.

Dokumen ini adalah pasangan khusus CSV import dari [Sales API Architecture Flow](sales-api-architecture-flow.md). Dokumen tersebut memakai contoh `POST /api/sales` (create sale JSON), sedangkan dokumen ini memakai `POST /api/sales/import-csv`.

## Gambaran besar

Import CSV tetap berada pada **command/write side**, karena endpoint ini membuat sale baru. Perbedaannya dengan create sale JSON adalah adanya tahap preprocessing CSV sebelum data dikirim sebagai command ke application layer.

```text
Client
  |
  | multipart/form-data: file
  v
POST /api/sales/import-csv
  |
  v
ImportSalesCsvRequest
  |-- validasi file upload
  v
SalesController::importCsv()
  |
  v
ImportSalesCsvAction
  |-- parse CSV dan validasi per record
  |-- group record berdasarkan import_ref
  |-- validasi antarrecord dalam group
  |-- buat CreateSaleCommand per group
  v
CommandBusInterface::dispatch()
  |
  v
CreateSaleHandler
  |-- cek customer melalui port
  |-- resolve product dan LineItem melalui port
  |-- Sale::create()
  |-- SaleRepositoryInterface::store()
  v
Infrastructure adapters / database / domain events
  |
  v
SalesCsvImportRes
  |
  v
HTTP JSON: 201 atau 422
```

## Layer dan batas tanggung jawab

| Layer | Class utama | Tanggung jawab dalam import CSV |
| --- | --- | --- |
| Routing | [`Routes/api.php`](../Routes/api.php#L45-L58) | Mendaftarkan endpoint dan target controller. |
| HTTP request | [`ImportSalesCsvRequest`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28) | Memvalidasi bentuk file upload. |
| HTTP controller | [`SalesController`](../Apps/Api/Sales/SalesController.php#L27-L112) | Memanggil action dan memilih status HTTP. |
| API/use-case adapter | [`ImportSalesCsvAction`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L25-L383) | Mengubah file CSV menjadi kumpulan command dan summary response. |
| Application | [`CreateSaleCommand`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26), [`CreateSaleHandler`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L17-L55) | Menjalankan use case pembuatan satu sale untuk setiap group valid. |
| Domain | [`Sale`](../Src/Sales/Domain/Entities/Sale.php), value objects, domain exceptions | Menjaga aturan/invariant sale dan mencatat domain event. |
| Infrastructure | Repository dan adapter port | Mengecek data eksternal, menyimpan sale, dan mem-publish event. |

`ImportSalesCsvAction` tidak melakukan query Eloquent secara langsung. Action hanya mem-parsing data, membuat command, lalu menyerahkan aturan create sale ke application/domain melalui command bus.

## Entry point: Sales CSV Import API

Route import berada di [`Routes/api.php`](../Routes/api.php#L45-L58):

```php
Route::prefix('sales')->group(function () {
    Route::post('/import-csv', [SalesController::class, 'importCsv']);
});
```

Endpoint:

```http
POST /api/sales/import-csv
Content-Type: multipart/form-data
Accept: application/json
```

Form field yang dikirim:

```text
file = <sales.csv>
```

Format CSV minimal:

```csv
import_ref,customer_id,product_id,quantity
sale-001,customer-ulid,product-ulid,2
sale-001,customer-ulid,another-product-ulid,1
sale-002,another-customer-ulid,product-ulid,3
```

Satu record CSV berarti satu line item. Record dengan `import_ref` yang sama digabung sebagai satu sale.

---

# Flow command side: import CSV sales

## 1. Client mengunggah file CSV

Client mengirim request `multipart/form-data` ke endpoint import.

```http
POST /api/sales/import-csv
Content-Type: multipart/form-data

file=@sales.csv
```

Pada tahap ini belum ada sale dibuat. Laravel terlebih dahulu melakukan route matching dan request validation.

## 2. Route mengarah ke controller

File: [`Routes/api.php`](../Routes/api.php#L45-L58)

```php
Route::post('/import-csv', [SalesController::class, 'importCsv']);
```

Route mengarahkan request ke [`SalesController::importCsv()`](../Apps/Api/Sales/SalesController.php#L38-L47).

## 3. Request object memvalidasi file upload

File: [`ImportSalesCsvRequest.php`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28)

Sebelum method controller berjalan, Laravel membuat dan memvalidasi [`ImportSalesCsvRequest`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28). Class ini mewarisi [`AbstractFormRequest`](../Apps/Shared/Http/AbstractFormRequest.php#L9-L20), yang berbasis Laravel `FormRequest`.

Aturan dari [`ImportSalesCsvRequest::rules()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L15-L20):

```php
'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
```

Artinya:

1. Field `file` wajib dikirim.
2. Nilainya harus berupa uploaded file.
3. File harus dikenali sebagai `csv` atau `txt`.
4. Ukuran file maksimum 2048 KB.

Jika validasi ini gagal, Laravel mengembalikan HTTP `422` dan flow berhenti. [`SalesController::importCsv()`](../Apps/Api/Sales/SalesController.php#L38-L47) serta [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L36-L177) belum dijalankan.

Tanggung jawab request object hanya memvalidasi **transport HTTP/file**. Validasi header, field CSV, grouping, dan aturan sale tidak berada di class ini.

## 4. Controller menerima file dan memanggil action

File: [`SalesController.php`](../Apps/Api/Sales/SalesController.php#L38-L47)

```php
public function importCsv(
    ImportSalesCsvRequest $request,
    ImportSalesCsvAction $action,
): JsonResponse {
    $resource = $action(new ImportSalesCsvDto(file: $request->getUploadedFile()));
    $status = $resource->failed_rows === 0 ? 201 : 422;

    return response()->json($resource, $status);
}
```

Controller melakukan empat hal:

1. Menerima [`ImportSalesCsvRequest`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28) yang telah lolos validasi Laravel.
2. Mengambil [`UploadedFile`](https://laravel.com/docs/requests#retrieving-uploaded-files) memakai [`ImportSalesCsvRequest::getUploadedFile()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L22-L28).
3. Membungkus file dalam [`ImportSalesCsvDto`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvDto.php#L9-L15), lalu memanggil action.
4. Mengubah [`SalesCsvImportRes`](../Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php#L9-L23) menjadi JSON dengan status `201` bila tidak ada baris gagal, atau `422` bila ada error import.

Controller tidak membaca CSV, tidak membuat `Sale`, dan tidak mengakses database secara langsung.

## 5. Action mem-parsing CSV

File: [`ImportSalesCsvAction.php`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L25-L383)

Entry point action adalah [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L36-L177). Pertama, fungsi ini memanggil [`ImportSalesCsvAction::parseCsv()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L182-L280).

```text
ImportSalesCsvDto
  |
  v
parseCsv(file)
  |
  +--> valid rows: SalesCsvRowDto[]
  +--> field/header errors: error[]
  +--> total non-empty data records: totalRows
```

### Tanggung jawab `parseCsv()`

[`ImportSalesCsvAction::parseCsv()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L182-L280) melakukan urutan ini:

1. Mendapatkan path file temporer dari `UploadedFile`.
2. Membuka file dan membaca header pertama menggunakan `fgetcsv()`.
3. Menormalisasi header menjadi lowercase dan trimmed.
4. Memastikan header `import_ref`, `customer_id`, `product_id`, serta `quantity` tersedia.
5. Membaca semua record data CSV satu per satu.
6. Melewati baris yang sepenuhnya kosong.
7. Menambah `totalRows` untuk setiap record data non-kosong.
8. Membaca nilai kolom dengan [`ImportSalesCsvAction::readColumn()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L293-L302).
9. Memvalidasi setiap nilai dengan [`ImportSalesCsvAction::validateRow()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L307-L375).
10. Mengubah record valid menjadi [`SalesCsvRowDto`](../Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php#L10-L20); atau menyimpan detail error record invalid.

Hasil fungsi:

```php
[$rows, $parseErrors, $totalRows]
```

| Nilai | Arti |
| --- | --- |
| `$rows` | Hanya record yang lolos validasi field. |
| `$parseErrors` | Error header atau field, dapat berisi lebih dari satu error untuk record yang sama. |
| `$totalRows` | Semua record data yang tidak kosong, termasuk record invalid. Header tidak dihitung. |

### Validasi satu record CSV

[`ImportSalesCsvAction::validateRow()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L307-L375) menerapkan aturan berikut:

| Field | Validasi |
| --- | --- |
| `import_ref` | Wajib ada. |
| `customer_id` | Wajib ada dan harus UUID sesuai [`ImportSalesCsvAction::isUuid()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L377-L383). |
| `product_id` | Wajib ada dan harus UUID sesuai [`ImportSalesCsvAction::isUuid()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L377-L383). |
| `quantity` | Wajib ada, digit integer, dan lebih besar dari nol. |

Satu record dapat menghasilkan beberapa item `errors`. Contoh: `customer_id` invalid dan `quantity` nol menghasilkan dua detail error untuk nomor record CSV yang sama.

### Early return: ada error parsing/field

Pada [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L40-L49), jika `$parseErrors` tidak kosong:

```text
CSV/header/field invalid
  |
  v
SalesCsvImportRes
  |
  +--> created_sales = 0
  +--> sales = []
  +--> errors = parseErrors
  v
SalesController::importCsv()
  |
  v
HTTP 422 JSON
```

Action tidak mencoba grouping, tidak membuat invoice, tidak membuat command, dan tidak menulis data saat terdapat error parser/field.

## 6. Action mengelompokkan record valid berdasarkan `import_ref`

Setelah parsing bersih, action menjalankan blok grouping pada [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L51-L100).

```text
SalesCsvRowDto[]
  |
  v
Groups by import_ref
  |
  +--> one customer_id per group
  +--> unique product_id per group
```

Satu group menyimpan:

- `customer_id` dari record pertama;
- `first_row`, yaitu nomor record CSV pertama pada group;
- `rows`, yaitu seluruh [`SalesCsvRowDto`](../Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php#L10-L20) dalam group;
- `products`, map untuk mendeteksi `product_id` duplikat.

Aturan antarrecord:

1. Semua record dengan `import_ref` yang sama wajib memakai `customer_id` yang sama.
2. Satu `product_id` tidak boleh muncul lebih dari sekali pada `import_ref` yang sama.

Jika aturan ini dilanggar, action menyimpan `$groupErrors` lalu berhenti pada [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L92-L100). Tidak ada group yang didispatch. Ini menjaga import bersifat atomic pada validasi format dan struktur CSV.

## 7. Action membuat command untuk setiap group

Jika seluruh record dan group valid, action melanjutkan ke blok dispatch pada [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L102-L168).

Flow untuk satu `import_ref`:

```text
CSV group
  |
  +--> CustomerId::fromString(customer_id)
  +--> CreateSaleLineItem[]
  +--> SaleId::random()
  +--> InvoiceNumberGeneratorInterface::next()
  v
CreateSaleCommand
  |
  v
CommandBusInterface::dispatch()
```

Class/fungsi yang dipakai:

1. [`CustomerId`](../Src/Sales/Domain/ValueObjects/CustomerId.php) dibuat dari customer group.
2. Setiap [`SalesCsvRowDto`](../Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php#L10-L20) diubah menjadi [`CreateSaleLineItem`](../Src/Sales/Application/Commands/Create/CreateSaleLineItem.php#L13-L20).
3. [`SaleId::random()`](../Src/Sales/Domain/ValueObjects/SaleId.php) membuat identitas sale server-side.
4. [`InvoiceNumberGeneratorInterface::next()`](../Src/Sales/Domain/Ports/InvoiceNumberGeneratorInterface.php#L15-L24) menghasilkan invoice number server-side.
5. [`CreateSaleCommand`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26) membawa data sale ke application layer.
6. `CommandBusInterface` menerima command melalui `dispatch()`.

`import_ref` tidak disimpan sebagai `sale_id` maupun `invoice_number`. Ia hanya key pengelompokan dalam file CSV dan dipakai pada response untuk memetakan hasil kembali ke client.

## 8. Command bus meneruskan command ke handler

Command bus menerima [`CreateSaleCommand`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26) dan meneruskannya ke [`CreateSaleHandler`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L17-L55).

```text
CreateSaleCommand
  |
  v
CommandBusInterface::dispatch()
  |
  v
CreateSaleHandler::__invoke()
```

Import CSV tidak mempunyai handler khusus. Ia memakai handler create sale yang sama dengan flow `POST /api/sales`. Dengan demikian business rule pembuatan sale tetap berada di satu tempat.

## 9. Application handler menjalankan use case create sale

File: [`CreateSaleHandler.php`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L17-L55)

[`CreateSaleHandler::__invoke()`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L27-L55) melakukan:

1. Memastikan customer ada melalui `CustomerExistenceCheckerInterface`.
2. Mengubah setiap [`CreateSaleLineItem`](../Src/Sales/Application/Commands/Create/CreateSaleLineItem.php#L13-L20) menjadi domain `LineItem` lewat `ProductCatalogInterface`.
3. Menggunakan invoice number dari command. Jika command tidak berisi invoice, handler meminta invoice baru melalui `InvoiceNumberGeneratorInterface`.
4. Memanggil [`Sale::create()`](../Src/Sales/Domain/Entities/Sale.php) untuk membuat aggregate.
5. Menyimpan aggregate melalui `SaleRepositoryInterface`.

```text
CreateSaleHandler
  |
  +--> CustomerExistenceCheckerInterface.exists(customerId)
  +--> ProductCatalogInterface.lineItemFor(productId, quantity)
  +--> Sale::create(...)
  +--> SaleRepositoryInterface.store(sale)
```

Handler bergantung pada port/interface, bukan Eloquent model. Implementasi detail customer lookup, product lookup, persistence, dan event publication berada di infrastructure layer.

## 10. Domain dan infrastructure menyimpan sale

Domain aggregate [`Sale`](../Src/Sales/Domain/Entities/Sale.php) menerima data yang telah di-resolve handler dan menerapkan invariant business sale. Aggregate juga mencatat domain event ketika sale dibuat.

Handler hanya mengetahui [`SaleRepositoryInterface`](../Src/Sales/Domain/Repositories/SaleRepositoryInterface.php). Implementasinya adalah [`SaleRepository`](../Src/Sales/Infrastructure/Persistence/SaleRepository.php), yang bertanggung jawab menyimpan sale serta line item, kemudian mem-publish domain event yang dicatat aggregate.

Rantai dependency:

```text
CreateSaleHandler
  |
  v
SaleRepositoryInterface
  |
  v
SaleRepository (infrastructure)
  |
  +--> sales table
  +--> sale line items table
  +--> domain event bus
```

## 11. Hasil dispatch kembali ke action

Jika satu group berhasil diproses, action menambahkan mapping berikut ke `$sales`:

```json
{
  "import_ref": "sale-001",
  "sale_id": "generated-sale-id",
  "invoice_number": "generated-invoice-number"
}
```

Jika pembuatan value object atau dispatch group gagal, [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L106-L160) menangkap exception tersebut dan menambahkannya ke `$dispatchErrors` dengan `first_row` dari group.

Berbeda dari error parsing/grouping, error pada tahap dispatch tidak menghentikan loop group. Action masih mencoba mengimpor group valid berikutnya. Karena itu tahap dispatch dapat menghasilkan partial success: sebagian sale telah dibuat, sementara group lain ada di `errors`.

## 12. Action membuat summary response

File: [`SalesCsvImportRes.php`](../Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php#L9-L23)

Pada akhir action, [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L170-L176) membangun [`SalesCsvImportRes`](../Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php#L9-L23):

```text
SalesCsvImportRes
  |
  +--> total_rows
  +--> created_sales
  +--> failed_rows
  +--> sales
  +--> errors
```

Arti field:

| Field | Arti |
| --- | --- |
| `total_rows` | Jumlah semua record data CSV non-kosong. Header dan baris kosong tidak dihitung. Record invalid tetap dihitung. |
| `created_sales` | Jumlah group `import_ref` yang sukses menjadi sale. Bukan jumlah record CSV. |
| `failed_rows` | Jumlah nomor record CSV unik yang memiliki error. |
| `sales` | Mapping `import_ref`, `sale_id`, dan `invoice_number` untuk group sukses. |
| `errors` | Detail error field, group, atau dispatch. Jumlahnya bisa melebihi `failed_rows`. |

[`ImportSalesCsvAction::countFailedRows()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L282-L288) mendeduplikasi nilai `row` pada `errors`. Contoh: enam record gagal dengan total tiga belas detail field error menghasilkan `failed_rows: 6`, bukan `13`.

## 13. Controller mengirim response HTTP

Control kembali ke [`SalesController::importCsv()`](../Apps/Api/Sales/SalesController.php#L42-L46):

```text
SalesCsvImportRes
  |
  +--> failed_rows === 0 --> HTTP 201 Created
  |
  +--> failed_rows > 0  --> HTTP 422 Unprocessable Entity
```

Contoh seluruh import sukses:

```json
{
  "total_rows": 3,
  "created_sales": 2,
  "failed_rows": 0,
  "sales": [
    {
      "import_ref": "sale-001",
      "sale_id": "generated-sale-id-1",
      "invoice_number": "generated-invoice-number-1"
    },
    {
      "import_ref": "sale-002",
      "sale_id": "generated-sale-id-2",
      "invoice_number": "generated-invoice-number-2"
    }
  ],
  "errors": []
}
```

Contoh CSV invalid:

```json
{
  "total_rows": 2,
  "created_sales": 0,
  "failed_rows": 1,
  "sales": [],
  "errors": [
    {
      "row": 2,
      "import_ref": "sale-001",
      "field": "quantity",
      "message": "quantity must be an integer greater than zero."
    }
  ]
}
```

## Ringkasan alur dan class terkait

```text
HTTP multipart request
  -> Routes/api.php
  -> ImportSalesCsvRequest
  -> SalesController::importCsv()
  -> ImportSalesCsvDto
  -> ImportSalesCsvAction::parseCsv()
  -> SalesCsvRowDto[] / errors
  -> grouping by import_ref
  -> CreateSaleCommand per group
  -> CommandBusInterface::dispatch()
  -> CreateSaleHandler::__invoke()
  -> Sale aggregate + ports + repository
  -> SalesCsvImportRes
  -> JSON HTTP 201 / 422
```

## Perubahan saat menambah input CSV dalam DDD

CSV import adalah **input adapter baru** untuk use case create sale yang sudah ada. Implementasi tidak membuat domain model, repository, atau command handler baru hanya karena format input berubah.

```text
Create sale JSON
HTTP JSON -> CreateSaleRequest -> CreateSaleAction
          -> CreateSaleCommand -> CreateSaleHandler -> Sale -> Repository

Create sale CSV
HTTP multipart -> ImportSalesCsvRequest -> ImportSalesCsvAction
               -> parse + validate + group CSV
               -> CreateSaleCommand -> CreateSaleHandler -> Sale -> Repository
```

### Komponen baru khusus CSV

| Area | File | Tanggung jawab tambahan |
| --- | --- | --- |
| Routing | [`Routes/api.php`](../Routes/api.php#L45-L58) | Menambahkan `POST /api/sales/import-csv`. |
| Request validation | [`ImportSalesCsvRequest`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28) | Memvalidasi upload `file` sebagai CSV/TXT dengan batas ukuran. |
| Input DTO | [`ImportSalesCsvDto`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvDto.php#L9-L15) | Mentransfer `UploadedFile` dari HTTP controller ke action. |
| CSV parser/orchestrator | [`ImportSalesCsvAction`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L25-L383) | Membaca CSV, memvalidasi header dan record, menghitung summary, melakukan grouping `import_ref`, lalu membangun command per group. |
| Row DTO | [`SalesCsvRowDto`](../Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php#L10-L20) | Mewakili satu record CSV yang lolos validasi field. |
| Import response | [`SalesCsvImportRes`](../Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php#L9-L23) | Mengembalikan `total_rows`, `created_sales`, `failed_rows`, hasil sale, dan error per record/group. |

### Komponen create sale yang tetap digunakan

| Layer | File/komponen | Pemakaian oleh CSV import |
| --- | --- | --- |
| Command | [`CreateSaleCommand`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26) | Dibuat satu kali untuk setiap group `import_ref` yang valid. |
| Command item | [`CreateSaleLineItem`](../Src/Sales/Application/Commands/Create/CreateSaleLineItem.php#L13-L20) | Dibentuk dari setiap record CSV dalam group. |
| Command bus | `CommandBusInterface::dispatch()` | Mengirim command CSV ke handler use case yang sama. |
| Application handler | [`CreateSaleHandler::__invoke()`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L27-L55) | Memastikan customer ada, resolve product menjadi domain line item, membuat sale, dan menyimpannya. |
| Domain | [`Sale`](../Src/Sales/Domain/Entities/Sale.php), value objects, domain exceptions | Invariant sale dan domain event tetap sama untuk input JSON maupun CSV. |
| Ports | [`InvoiceNumberGeneratorInterface`](../Src/Sales/Domain/Ports/InvoiceNumberGeneratorInterface.php#L15-L24), customer checker, product catalog | Menghasilkan invoice serta memeriksa customer/product melalui abstraction yang sama. |
| Persistence | [`SaleRepository`](../Src/Sales/Infrastructure/Persistence/SaleRepository.php) | Menyimpan aggregate dan line item memakai persistence/event flow yang sama. |

Setelah [`ImportSalesCsvAction::__invoke()`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L36-L177) membentuk [`CreateSaleCommand`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26), alurnya identik dengan create sale JSON. Perbedaan hanya berada pada tahap penerjemahan input sebelum command dibuat; business rule sale tidak diduplikasi di parser CSV.

---

File inti:

1. [`Routes/api.php`](../Routes/api.php#L45-L58)
2. [`Apps/Api/Sales/SalesController.php`](../Apps/Api/Sales/SalesController.php#L38-L47)
3. [`Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvRequest.php#L10-L28)
4. [`Apps/Api/Sales/ImportCsv/ImportSalesCsvDto.php`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvDto.php#L9-L15)
5. [`Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php`](../Apps/Api/Sales/ImportCsv/ImportSalesCsvAction.php#L25-L383)
6. [`Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php`](../Apps/Api/Sales/ImportCsv/SalesCsvRowDto.php#L10-L20)
7. [`Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php`](../Apps/Api/Sales/ImportCsv/SalesCsvImportRes.php#L9-L23)
8. [`Src/Sales/Application/Commands/Create/CreateSaleCommand.php`](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php#L13-L26)
9. [`Src/Sales/Application/Commands/Create/CreateSaleHandler.php`](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php#L17-L55)
10. [`Src/Sales/Domain/Entities/Sale.php`](../Src/Sales/Domain/Entities/Sale.php)
11. [`Src/Sales/Infrastructure/Persistence/SaleRepository.php`](../Src/Sales/Infrastructure/Persistence/SaleRepository.php)
