(function (window) {
  "use strict";
const skrFormSections = [
    {
      key: "branding",
      title: "Branding",
      fields: [
        "company_logo_url",
        "skr_company_name",
        "skr_company_rc",
        "skr_license_number",
        "skr_company_phone",
        "skr_company_email",
        "skr_company_address",
      ],
    },
    { key: "depositor", title: "Depositor Information", fields: ["depositor_name", "client_email", "client_address"] },
    {
      key: "custody",
      title: "Custody Details",
      fields: [
        "custody_type",
        "projected_days",
        "documented_custom_value",
        "represented_date",
        "represented_by",
        "receiving_officer",
      ],
    },
    {
      key: "contents",
      title: "Contents Details",
      fields: ["content_description", "quantity", "unit", "packages_number", "declared_value", "origin_of_goods"],
    },
    {
      key: "deposit",
      title: "Deposit Details",
      fields: ["deposit_type", "total_value", "insurance_rate", "storage_fees_label", "date_label"],
    },
    { key: "docs", title: "Supporting Documents", fields: ["supporting_documents"] },
    { key: "instructions", title: "Deposit Instructions", fields: ["deposit_instructions"] },
    {
      key: "additional",
      title: "Additional Information",
      fields: [
        "additional_notes",
        "depositor_signature",
        "affidavit_text",
        "issuer_name",
        "issuer_title",
        "stamp_label",
        "watermark_enabled",
        "bitcoin_enabled",
      ],
    },
  ];
  const invoiceFormSections = [
    {
      key: "branding",
      title: "Branding",
      fields: ["company_logo_url", "invoice_date", "watermark_enabled"],
    },
    {
      key: "client",
      title: "Client and Shipment",
      fields: ["client_name", "client_email", "client_address", "destination", "cargo_type", "currency", "quantity", "unit", "purity", "carats", "taxable_value"],
    },
    {
      key: "payment",
      title: "Payment Details",
      fields: ["payment_wallet_address", "payment_network", "bitcoin_enabled"],
    },
    {
      key: "cost_rates",
      title: "Cost Rates and Fees",
      fields: ["tax_rate", "insurance_rate", "smelting_cost", "cert_origin", "cert_ownership", "export_permit", "freight_cost", "agent_fees"],
    },
  ];
  const receiptFormSections = [
    {
      key: "branding",
      title: "Branding and Header",
      fields: ["company_logo_url", "receipt_title", "receipt_date", "watermark_enabled"],
    },
    {
      key: "client",
      title: "Billed To",
      fields: ["client_name", "client_email", "client_address", "current_location"],
    },
    {
      key: "commodity",
      title: "Commodity",
      fields: ["line_items", "amount_paid", "currency"],
    },
    {
      key: "payment",
      title: "Payment Details",
      fields: ["payment_method", "payment_reference", "payment_wallet_address", "payment_network", "bitcoin_enabled"],
    },
    {
      key: "bank",
      title: "Bank Details",
      fields: ["bank_name", "bank_account_number", "bank_account_name", "bank_swift_code", "bank_address"],
    },
    {
      key: "footer",
      title: "Footer and Notes",
      fields: ["notes", "company_phone", "company_email", "company_website", "company_address", "wet_stamp_note"],
    },
  ];
  const invoiceFieldUi = {
    currency: { control: "select", options: ["USD", "EUR", "GBP", "UGX"] },
    unit: { control: "select", options: ["KGS", "KG", "GRAMS", "TONNES"] },
    payment_network: { control: "select", options: ["TRON (TRC20)", "Bitcoin", "USDT (ERC20)", "USDT (TRC20)"] },
  };
  const receiptFieldUi = {
    currency: { control: "select", options: ["USD", "EUR", "GBP", "UGX"] },
    payment_method: { control: "select", options: ["Bank Transfer", "Cash", "Bitcoin", "USDT"] },
    payment_network: { control: "select", options: ["Bitcoin", "TRON (TRC20)", "USDT (TRC20)", "USDT (ERC20)"] },
  };
  const skrFieldUi = {
    custody_type: { control: "select", options: ["SAFE CUSTODY", "SINGLE CUSTODY", "JOINT CUSTODY"] },
    content_description: { control: "select", options: ["Precious Metal", "Raw Gold", "Mineral", "General Cargo"] },
    unit: { control: "select", options: ["KGS", "Kilograms (kgs)", "Boxes", "Units"] },
    origin_of_goods: { control: "select", options: ["Uganda", "D.R.Congo", "Kenya", "Tanzania", "Rwanda"] },
    deposit_type: { control: "select", options: ["Bonded Warehouse", "Mineral", "General Deposit"] },
    supporting_documents: { control: "checkboxes", options: ["PRELIMINARY DOCUMENTATION", "CERTIFICATE OF ORIGIN", "CERTIFICATE OF OWNERSHIP", "EXPORT PERMIT"] },
  };
  window.CDS_DOCS_SECTIONS = {
    skrFormSections: skrFormSections,
    invoiceFormSections: invoiceFormSections,
    receiptFormSections: receiptFormSections,
    invoiceFieldUi: invoiceFieldUi,
    receiptFieldUi: receiptFieldUi,
    skrFieldUi: skrFieldUi,
  };
})(window);
