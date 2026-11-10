# Noah Digital Stores

Standalone traditional multi-page storefront. This project has no GitHub/GitHub Pages integration and does not require either. Host it on any web host that supports PHP 8.1+ with HTTPS and cURL. A static-only host cannot run the secure order endpoints.

## Important before launch

- The supplied legacy URL could not be accessed (it returned 404), so the catalogue in `assets/products.js` is seeded example content. Prices are illustrative TZS estimates, not verified business prices. Verify or replace product names, images, stock, specifications, prices, contact details and legal text before accepting customers.
- Checkout requires a sourcing-service selection: TZS 1,000 to request a product, or TZS 2,000 to find a product and seller. The customer must enter the payment reference/code and describe the goods. The website records the code as pending verification; it does not verify payment or start the service until Noah confirms it. Product prices and delivery are separate.
- Customer orders and enquiries are only recorded externally after Power Automate or Microsoft Graph has been configured. Until then the endpoints return a clear service-not-configured error; they do not pretend to have saved anything.
- Shopping bag and wishlist use browser local storage only for convenience. They are not the permanent records database.

## Pages

`index.html`, `shop.html`, `categories.html`, `deals.html`, `about.html`, `services.html`, `contact.html`, `how-to-order.html`, `delivery-payment.html`, `faq.html`, `blog.html`, `order-tracking.html`, `privacy-policy.html`, `terms.html`, `product-details.html`, `cart.html`, and `checkout.html` are independent HTML pages.

## Local preview

Install PHP 8.1+ and enable the cURL and mbstring extensions. From this folder, run:

```powershell
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000/index.html`. Checkout and enquiry submission will correctly report a configuration error until the private webhook variables are set. Do not use the PHP development server for public production hosting.

## Excel workbook

Generate a workbook template locally with built-in Windows PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\make_workbook.ps1
```

The generated `NOAH-DIGITAL-STORE-RECORDS-TEMPLATE.xlsx` has worksheets `CUSTOMERS`, `ORDERS`, `ORDER ITEMS`, `PRODUCTS`, `PAYMENTS`, and `ENQUIRIES`. Upload it to a business-controlled OneDrive for Business or SharePoint document library, then format each header/data range as an Excel Table. Use stable table names such as `Customers`, `Orders`, `OrderItems`, `Products`, `Payments`, and `Enquiries`. Keep workbook access limited to authorized business users.

## Power Automate secure integration

The supported starter integration uses Power Automate HTTP-trigger flows and Excel Online (Business), so Graph credentials and flow URLs stay on the PHP server. Create three flows:

1. **Order intake**: trigger on `When an HTTP request is received`; accept JSON with `type=order`, unique `reference`, `createdAt`, nested `customer`, `items`, delivery/payment preferences, `serviceType`, `goodsRequest`, `serviceFee`, `paymentReference`, `paymentStatus`, currency, estimated total and order status. Validate the service fee against the selected service (`product_request` = TZS 1,000; `product_and_seller_search` = TZS 2,000); do not accept a client-calculated fee. Upsert the customer by phone to `Customers`; add one row to `Orders`; add one `OrderItems` row per product; add a row to `Payments` with Amount=`serviceFee`, Currency=`TZS`, Method=`paymentPreference`, PaymentCode=`paymentReference`, and Status=`Reference submitted - pending verification`. Do not mark the payment received or begin sourcing until a staff member verifies it. Set the order status to `Awaiting payment verification`. Respond HTTP 200 with JSON `{ "ok": true }` only after all workbook writes succeed. On a failure, respond with a non-2xx code. Set flow access/authentication according to the tenant's available Power Automate options, and restrict the trigger to the website backend where possible.
2. **Enquiry intake**: same HTTP trigger pattern for `type=enquiry`; append the contact and message to `Enquiries`, optionally upsert `Customers`; respond `{ "ok": true }` only after the workbook write.
3. **Order tracking**: HTTP trigger accepts `{ "type":"track", "reference":"...", "phone":"..." }`; find an exact order reference (format `ND-YYYY-` plus 16 hexadecimal characters) in `Orders`, compare the supplied phone against the associated customer phone, and return only the matching order's status, created time and a brief item summary as `{ "found": true, "order": { "reference":"...", "status":"...", "createdAt":"...", "summary":"..." } }`. Return `{ "found": false }` when either does not match. Do not return address, email, payment details or other orders.

After validation, checkout immediately opens a prefilled WhatsApp order to Noah at `0745468020` (WhatsApp format `255745468020`) with the order number, requested goods, selected service, Halotel Tips account and payment reference. The same order number is submitted to the private PHP endpoint for Excel recording. Customers must still send the prefilled WhatsApp message themselves. If the external workbook is unavailable, the page says the save failed and leaves the WhatsApp message link available so the request can still reach Noah; it never claims that an unsaved request was recorded.

### Server environment variables

Configure these in the PHP hosting control panel or server environment (never in HTML/JavaScript):

- `NOAH_ORDER_WEBHOOK`: HTTPS URL for the private order-intake flow.
- `NOAH_ENQUIRY_WEBHOOK`: HTTPS URL for the private enquiry flow.
- `NOAH_TRACK_WEBHOOK`: HTTPS URL for the private tracking flow.

The PHP endpoints are `api/orders.php` (POST order, GET tracking) and `api/enquiries.php` (POST enquiry). Ensure HTTPS is enabled. Restrict PHP error logs and never print webhook URLs or request payloads to public responses. Some Power Automate plans require an authenticated trigger or API Management/gateway for production; configure according to the tenant's security policy.

## Hosting

1. Choose a PHP 8.1+ host with HTTPS, cURL, mbstring and environment-variable configuration (shared hosting or a managed PHP host).
2. Upload the contents of this folder to the site's document root using the host's secure deployment/file transfer method. This is independent of GitHub.
3. Configure the three environment variables in the host panel. Keep secrets out of the web root and source control.
4. Upload the workbook to OneDrive for Business/SharePoint, create tables, and configure the flows using the actual workbook/table IDs.
5. Test an order, a failed flow, an enquiry and tracking with a test order. Verify all six sheets and restrict workbook access.
6. Replace sample catalogue data in `assets/products.js` and `api/product-catalog.php` together. The server catalog is authoritative for order prices and item names. Update both files whenever a product changes. Move catalogue to a server-managed source if frequent edits are needed.
7. Have the business review `privacy-policy.html`, `terms.html`, delivery/payment wording and prices before public launch.

## Notes

The workbook is external and is never placed in the public site. The Excel template is a local setup artifact only; upload it to the business's Microsoft 365 storage. The site has no admin dashboard, customer account requirement, payment processing, GitHub dependency or public status-update endpoint.
