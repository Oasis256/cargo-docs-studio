(function (window) {
  "use strict";
const payloadDefaults = {
    invoice: {
      company_logo_url: "https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png",
      invoice_date: "2026-02-22",
      client_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      destination: "Kampala, Uganda",
      cargo_type: "Electronics",
      currency: "USD",
      quantity: 2,
      unit: "KGS",
      purity: "",
      carats: "",
      taxable_value: 900.5,
      payment_wallet_address: "TBACargoWalletAddress",
      payment_network: "TRON (TRC20)",
      tax_rate: 5,
      insurance_rate: 1.5,
      smelting_cost: 0,
      cert_origin: 0,
      cert_ownership: 0,
      export_permit: 0,
      freight_cost: 0,
      agent_fees: 0,
      watermark_enabled: true,
      bitcoin_enabled: true,
    },
    receipt: {
      company_logo_url: "https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png",
      receipt_title: "PAYMENT RECEIPT",
      receipt_date: "2026-02-22",
      client_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      cargo_type: "Paid Cargo",
      currency: "USD",
      line_items: [
        { description: "Paid Cargo", quantity: 1, amount: 500, one_time: false },
      ],
      payment_method: "Bitcoin",
      payment_reference: "",
      payment_wallet_address: "TBACargoWalletAddress",
      payment_network: "Bitcoin",
      bank_name: "ECO BANK UGANDA LIMITED",
      bank_account_number: "7170009076",
      bank_account_name: "WAKALA MINERALS LIMITED",
      bank_swift_code: "ECOCUGKA",
      bank_address: "PLOT 8A KAFU ROAD KAMPALA UGANDA",
      company_phone: "+256-765242719",
      company_email: "info@wakalaminerals.com",
      company_website: "www.wakalaminerals.com",
      company_address: "PLOT 429 SSEGUKU P.O.BOX 124439 KAMPALA-CPO",
      wet_stamp_note: "Valid Only If Wet Stamped.",
      current_location: "Collection Desk",
      watermark_enabled: true,
      bitcoin_enabled: true,
    },
    skr: {
      company_logo_url: "https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png",
      skr_company_name: "WAKALA MINERALS LIMITED",
      skr_company_rc: "1234567",
      skr_license_number: "77-7477",
      skr_company_phone: "+256 778 223 344",
      skr_company_email: "info@wakalaminerals.com",
      skr_company_address: "PLOT 32A KAMPALA ROAD UGANDA",
      custody_type: "SAFE CUSTODY",
      depositor_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      projected_days: 30,
      documented_custom_value: "T.B.A",
      represented_date: "",
      represented_by: "N/A",
      receiving_officer: "MR.KIMBUGWE FAISAL",
      content_description: "Raw Gold",
      quantity: 250,
      unit: "KGS",
      packages_number: 2,
      declared_value: 120000,
      origin_of_goods: "Uganda",
      deposit_type: "Bonded Warehouse",
      total_value: "TBA",
      insurance_rate: "1.5%",
      supporting_documents: "PRELIMINARY DOCUMENTATION\nCERTIFICATE OF ORIGIN\nCERTIFICATE OF OWNERSHIP\nEXPORT PERMIT",
      storage_fees_label: "PER DAY = $25",
      deposit_instructions: "Release only to authorized signatory.",
      date_label: "",
      watermark_enabled: true,
      skr_watermark_enabled: true,
      depositor_signature: "",
      additional_notes: "Goods held under bonded terms.",
      affidavit_text: "The Depositors named here is the authorized signatory and is in full control of goods in our safe keepingand we are prepared to avail, release and/or deliver the deposited goods to the depositor or his/herassociates and heirs per his/her instructions in accordance with the terms of this safe keeping agreement.",
      issuer_name: "Kimbugwe Faisal",
      issuer_title: "ISSUING OFFICER LIMITED",
      stamp_label: "Official Stamp / Signature",
      bitcoin_enabled: false,
    },
    spa: {
      company_logo_url: "https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png",
      watermark_enabled: false,
      watermark_url: "",
      seller_initials: "Seller's Initials",
      buyer_initials: "Buyer's Initials",
      spa_tables: [
        {
          title: "THE SELLER",
          rows: [
            ["Name:", "Patrick Miancho Lubongo"],
            ["Address:", "Kloversvingen 3, 3050 Mjondalen"],
            ["Passport No:", "32662577"],
            ["Passport Expiry Date:", "03-07-2027"],
            ["Country of Issue:", "Norway"],
            ["Telephone:", "+47 45522854"],
            ["E-Mail:", "patrickdidicofu@yahoo.com"],
          ],
        },
        {
          title: "AND",
          rows: [
            ["Represented By:", "Tania Cescutti"],
            ["Passport Number:", "553959427"],
            ["Passport Expiry Date:", "17-04-2028"],
            ["Country of Issue:", "United Kingdom"],
          ],
        },
        {
          title: "BUYER'S PAYMENT COORDINATES",
          rows: [
            ["Bank Name:", "ABSA Bank"],
            ["Bank Address:", "1st Floor South Building C, Pretoria Campus, 270 Maggs Street Watloo, Pretoria, 0184, South Africa"],
            ["Branch:", "Pretoria"],
            ["SWIFT Code:", "ABSAZAJJ"],
            ["Account Name / Code:", "Tradefin International"],
            ["Account Number:", "374181-USD-1046-10"],
            ["IBAN:", ""],
          ],
        },
      ],
      spa_text_walls: [
        {
          title: "",
          body: `SALE AND PURCHASE AGREEMENT FOR 100 KGS
OF GOLD BARS - DUBAI - DELIVERY (CIF).
This Sales & Purchase Agreement is entered into and executed on
11th January 2026 by and between the following Parties:
THE SELLER
Name:
Patrick Miancho Lubongo
Address:
Kløversvingen 3, 3050 Mjøndalen
Passport No:
32662577
Passport Expiry Date:
03-07-2027
Country of Issue:
Norway
Telephone:
+47 45522854
E-Mail:
patrickdidicofu@yahoo.com
:
Hereinafter referred as the “SELLER”.
AND
Represented By:
Tania Cescutti
Passport Number:
553959427
Passport Expiry Date:
17-04-2028
United Kingdom
Country of Issue:
Hereinafter referred as the “BUYER”.
Seller’s Initials
Buyer’s Initials
WHEREAS the Seller has available for sale “Gold Bars’’, hereinafter collectively
referred to as “Goods”.
WHEREAS the Seller warrants that he has access to and can cause the delivery of
the goods to the Buyer in the manner herein after stated.
WHEREAS the Seller is desirous of selling the goods to the Buyer and the
Buyer is ready to pay the Seller in the manner hereinafter stated.
NOW THEREFORE: All Parties agree to the Terms and Conditions as follows:
SCOPE OF THE CONTRACT:
a) The Seller, under full authority and responsibility, declares that he has
the capability and unrestricted right to sell the Goods and guarantees
that he is capable to export the Goods legally.
b) The Buyer, under full corporate authority and responsibility declares that
he and his associates have the full capability to purchase the Goods and
such purchases will be increased with options and extensions.
c) The buyer shall pay approx 7% upfront from the current total amount of
all the consignments which the agent will use to cover all the
government loyalties and taxes
Any excuse of delayed or partial payment is not accepted after the final
assay at the Buyer's destination and the value accepted by both Buyer
and Seller.
At no given time must either party divulge any information concerning
the contract, and the relationship of persons concerned to non-
participants, i.e. those who are not involved in this transaction. The
Buyer and Seller should under no circumstances deal directly or act in a
manner that is deemed to be circumventing the Supplier, Buyer, Agents,
and Consultant.
Buyer’s Initials
Seller’s Initials
GOODS/COMMODITY SPECIFICATIONS:
Commodity: Gold Bars
Origin: East-African community Gold Purity: 97%+, 23 karat
Quantity: 100kgs - Batch No. 1
Contract Monthly: 500 kgs / Monthly
Gross Price: USD 95,000 per Kilogram
Duration: 12 months
Full payment: Wire Transfer – see PAYMENT TERMS BELOW
Packing: Sealed Metal Export Package Boxes
DELIVERY TERMS:
a) The delivery terms for this S.P.A. agreement shall be on СIF shipping
conditions basis (by FULL CIF) from ENTEBBE INTERNATIONAL AIRPORT,
UGANDA, to the Buyer’s designated airport. The Buyer, or Buyer’s financier,
will pay for government royalty and logistics costs of the consignment.
b) The goods remain under the Seller’s name until the full value price of the gold
is paid to Seller’s Bank Account.
c) The seller will send out a commercial invoice and an airway bill (if goods are
shipped by cargo) to the buyer before the plane gets to the destination
airport, to allow the buyer to pre-clear the expensive cargo.
d) The Buyer will ensure all procedures have been followed and the actual
delivery was completed to the refinery. The Seller will have the option to
have his representatives present at the refinery to witness the breaking of
the seal before the gold box is opened.
e) Seller and buyer wait for final ASSAY, while they wait the stock will be with
seller and not buyer / As soon as ASSAY is out buyer and seller will meat
to confirm the assay is acceptable to Seller and Buyer
f) The Seller retains Legal Ownership of Goods until the payment in full is
received by Seller’s bank from the Buyer.
DESTINATION ADDRESS:
Delivery to Dubai (UAE):
Consignee details and address:
COMPANY: RAMCHANDR DIAMOND JEWELLERY L.L.C
TAX Registration No. 104599609500001 / Licence No. 1388429
ADDRESS: 0307 ZAROONI BUILDING, AL MARARR, DEIRA, DUBAI, 0000,
United Arab Emirates. CONTACT NUMBER: +971565405315
Buyer’s Initials
Seller’s Initials
PAYMENT TERMS:
Payment will be done on Gold content less discount and not on weight.
Final payment for the Goods shall be made by Wire Transfer from the Buyer’s
BANK ACCOUNT to the Seller’s DESIGNATED BANK within 72 hours after
receipt of the final assay report from the refinery. The bank account that the
Buyer will use to wire out the money to the Seller is indicated here below.
Payment terms as per addendum B1
TRANSACTION TERMS AND PROCEDURES (CHRONICLE ORDER):
a)
b)
c)
d)
The delivery terms for this S.P.A. shall be CIF to the Buyer’s destination port.
For the initial (first) GOLD BARS shipment, Buyer/Buyer’s representative
shall meet the Seller in Kampala, Uganda to inspect the GOLD physically.
Buyer shall pay for costs pertaining government royalty and logistics costs
Invoice amount of the consignment here at the country of origin through the
seller’s agent.
From all shipments, the seller will directly export to the Buyer’s
destination and be paid 100% after all the GOLD BARS are refined into pure
bars OR assayed at independent Assay Lab at the Buyer’s refinery
or Lab in Dubai (destination).
During the Seller’s first shipment, a Seller’s representative will be physically
present at Customs and at the refinery to ensure all
procedures have been followed and actual delivery was completed to the
buyer refinery OR Lab. Buyer will notify Seller or Seller’s mandate for the
successful completion of events in written form.
Buyer’s Initials
Seller’s Initials
e)
Buyer will clear shipment of GOLD BARS through customs. Buyer will
arrange security to convey the product (GOLD BARS) from the airport to
his nominated refinery.
f)
Product (GOLD BARS) received for handling, homogenization, sampling
and analysis by the refinery shall be on the account of the Buyer. Standard
procedure in weighing, sampling, assaying must be observed for sampling
but assay is done in German Lab or equivalent.
g)
Both the Seller and Buyer shall have the right to appoint a representative
at their own expenses, to supervise the weighing, sampling and assaying
of the product (GOLD BARS).
Within two (2) working days of delivery of the GOLD BARS to the refinery or Lab,
the refinery or Lab will email a copy of the refinery or Lab’s assay report
(Inspectorate report) to the Buyer for acceptance and the Buyer will also fax or
email a copy of the refinery’s assay report to the Seller for acceptance.
APPLICABLE LAW AND JURISDICTION
Any controversy, dispute, or claim arising out of this Agreement shall be settled
according to Dubai / UAE laws. Ownership of the gold shall remain in seller’s
name until final payment is successfully affected by the buyer to the seller’s
bank.
The Buyer must arrange the WIRE transfer of total gold value price to the seller’s
Bank Account with in a maximum of 96 Hours after arrival of the gold at buyer’s
destination refinery.
The seller must transfer full ownership of the goods to buyer’s name/ownership
as soon as the wire
transfer is confirmed by Seller.
CLEARTITLE:
Seller confirms and warrants that the Title of the goods to be sold herein will be
free and clear of any and all Liens and encumbrances, and Seller states that
the gold is not of terrorist and/or criminal origin.
WARRANTIES:
a) The Buyer / Buyer’s Financier shall pay all the required duties and charges in
the country of export.
b) The Seller agrees to accept the final assay report from the Buyer’s designated
refinery, accepting payment of each shipment after final assay of the gold.
Buyer’s Initials
Seller’s Initials
COLLATERAL
The 10 kg single custody Collateral shall remain under custody until the
successful shipment of 100 kg to Dubai and verification of the remaining gold
quantity have been completed, and the Buyer has fulfilled all financial and
contractual obligations under the main sales and purchase agreement. Upon
fulfillment of these conditions, the Collateral shall be released to the Buyer. If
the 100 kg is not shipped within 14 days of the shipping date, the 10 kg will
become the property of the Buyer without fail.
NON-CIRCUMVENTION AND NON-DISCLOSURE
Buyer and Seller, and Carrier acknowledge that the harm to the other party
would be substantial and therefore the Seller and Buyer agree to abide by the
Customary International rules of non- circumvention and non-disclosure as
established by the International Chamber of Commerce in United Kingdom
(UK) for a period of five (5) years from the date hereof. Said non-
circumvention and non-disclosure shall include, but not be limited to
communicating with each other’s banks,
refiners, representatives of Buyer dealing with Customs, brokers or Seller’s
mandate. The understanding and accord of this subparagraph will survive the
termination of this Agreement.
TOTAL AGREEMENT
This Agreement supersedes any and all prior agreements and represents the
entire Agreement between the parties. No changes, alterations or
substitutions shall be permitted unless the same shall be notified in writing
and signed by both parties.
TERMS:
The terms of this Agreement shall be Confirmed and signed by the Buyer and
the Seller and carrier via facsimile or Email. Said executed facsimile or email
shall be binding and initiates and concludes the legal liabilities between Buyer
and Seller of this Agreement. By signing below, all parties abide by their
corporate and legal responsibility, and execute this Agreement under full
penalty of perjury. Any dispute or disagreement of any kind between the
signatories to this agreement shall be resolved by binding arbitration in
accordance with the laws of Dubai (UAE) and the arbitration tribunal shall
consist of three (3) arbitrators chosen by the parties from a slate of eight (8)
proposed arbitrators provided.
Buyer’s Initials
Seller’s Initials
FORCE MAJEURE:
The parties hereto shall not be held liable for any failure to perform under
the “Force Majeure” clause as regulated by the International Chamber of
Commerce, United Kingdom (UK) which clauses are deemed to be
incorporated herein.
Addendum terms:
Procedure / Delivery Terms / Scope of The Contract:
A) BUYER and SELLER seal, execute and sign this 'Purchase & Sale' agreement,
“S.P.A.”, in accordance with the Terms and Conditions set
forth therein.
B) The BUYER arranges for payment of the smelting fees for 110 kg shipment
after contract signing by both buyer and seller and product verification
and testing. The smelting fees is included in the total amount of
590,400.00 USD paid upfront to cover all the expenses according to the
invoice number: MMT257-XXX which the Seller’s agent MIDASGKO
MINERAL TRADERS will use to cover all the local expenses including
the government loyalties and Export taxes for Uganda as well as the
ICGLR needed for import to Dubai. - according to the CIF terms based on delivery to the Buyer’s designated port
of Import Dubai (UAE)
C) The gold delivery procedure establishes that the methodology applied is:
Inco Terms 2020 + Insurance.
D) For the first delivery of the Goods, the BUYER undertakes to purchase
Fifty Kilograms (100 kg) of gold bars in accordance with the SELLER
undertakes to deliver Fifty Kilograms (100 kg) of gold bars.
Buyer’s Initials
Seller’s Initials
E) Once the smelting and testing takes place, the AU GOLD (merchandise)
consignment is put into SINGLE custody by both parties in a nominated
security house by both parties and this will be the case from the smelting
period until the goods reach the buyer’s destination with Buyer’s company
being the consignee.
F) There will be a collateral issued to the buyer by the seller equivalent to
any deposit/funds disbursed by the buyer to the seller which shall be kept
under SINGLE custody in a bonded security warehouse nominated by both the
buyer and the seller. This will be secured through a CIF Collateral
Management Agreement .
G) The BUYER is required to make an upfront payment of 590,400.00 USD
of the consignment which will has been calculated according to the
Government price and an official invoice issued to the same effect. The
balance will be paid after final refining ASSAY report has been released from
the BUYER’S refinery – Al Etihad Gold Refinery in Dubai. The Deposit paid
from the Buyer will cater for Smelting, Government royalties
/ Taxes, Insurance, Freight as the final Consignment is transported using
airline Cargo transportation handled by SkyCargo from Entebbe ( EBB )
to Dubai ( UAE ).
H) To ensure the SELLER’S goods during the entire time they are in the BUYER’S
possession, the BUYER undertakes to take out a 'Goods Insurance' with 100%
coverage for the quantity of gold bars in
accordance with (Article 2) which will prevail from the time of delivery of the
metal to the BUYER’S care until the final destination and Certification
of the refining of the goods. The 'Goods Insurance' contract must state as the
'Beneficiary', the 'SELLER'. The goods belong to the seller until the seller
transfers the title of the goods to the 'Ultimate Purchaser of the Gold'.
I) The nominated security company handling all transactions for the Buyer on
the ground in
Dubai will be Etihad Secure Logistics under Buyer’s Identification
J) and account code: Tradefin International
K) BUYER will assist SELLER and its Agents in the Country with the delivery
of goods during the time necessary for the completion of the Gold operational
process.
Seller’s Initials
Buyer’s Initials
s
L) For all deliveries, an INVOICE will be issued in the name of the BUYER,
which will accompany the gold to the Refinery for refining. Upon completion
of the FINAL REFINING ASSAY REPORT, the seller will issue a new Invoice
in the name of the 'Ultimate Beneficiary of Gold Acquisition', detailing the
exact: Weight, Quality and Value, which will be calculated after final
refining certificate is issued. The Buyer will initiate all payments in
accordance with the terms and conditions of this contract. It will be the
seller's responsibility to confirm receipt of payments and transfer the Title
to the Gold.
M) SELLER shall provide the buyer with the following documents prior to export:
● Gold certificate.
● Commercial invoice.
● Certificate of ownership.
● Certificate of non-criminal origin and that the gold is free of
encumbrances.
● Packing list.
● Certificate of origin.
● Certificate of analysis and quality.
● Export certificate.
● ICGRL
Simultaneously with the final payment transferred to the Seller's account, the
Buyer undertakes to pay commissions to the Intermediaries declared in the IMFPA
Agreement which is materially incorporated into this Agreement as ANNEX 1.
A) All subsequent installments will be based on the same procedure (all
items), until the total quantity of the Contract has been completed, after
the execution of this Contract Agreement to its full extent, and, if the Buyer
decides to purchase more goods from the Seller, the Buyer and the Seller
shall enter into a new temporary contract, always respecting this
Agreement MMT257-  of transaction code number to be identified and
stated in the Invoice presented.
B) This S.P.A. contract will be valid for 14 (fourteen) days for its
execution, without delays and charges, counted from the date of signature
between the PARTIES.
Buyer’s Initials
Seller’s Initials
Article 6) Delivery documents: To be provided to the Buyer for the first
shipment before
Customs Clearance.
The Agent handling this consignment for the Seller will receive the goods at the
international airport
Entebbe (EBB) of destination in Kampala, Uganda.
All Delivery Documents must be maintained and transmitted via email in
accordance with the procedures set forth in this agreement.
● Internationally accepted test certificate after second refining issued by
the Buyer's chosen REFINERY, such certificate must be processed by the
Issuer.
● Commercial invoice issued by the Seller: THREE (3) originals and THREE
(3) original
copies in the name of the Buyer for transit between countries.
● The New Invoice must be prepared after presentation of the Final Test
Report applying the agreed upon price based for the purpose of payment
for the goods: Two (2) originals and two (2) original copies in the name
of the FINAL BENEFICIARY OF THE GOLD ACQUISITION.
● Certificate of Ownership: One (1) original and Three (3) issued copies.
● Packing list: one (1) original and three (3) issued copies stating:
● Certificate of Origin: One (1) original and Three (3) copies.
● Customs declaration that all taxes and other fees have been paid.
● Customs declaration and description of the weight list of the quantity of
boxes.
● The exporting company declares that all charges, taxes and any other
fees have been paid in full.
● Complete set Original Air Waybill, marked "Air Freight Prepaid": The Buyer.
● Certificate of 'Insurance A' clause: the buyer.
● ICGRL
Buyer’s Initials
Seller’s Initials
BUYER’S PAYMENT COORDINATES:
Bank Name:
ABSA Bank
Bank Address:
1st Floor South Building C, Pretoria Campus, 270 Maggs Street
Waltloo, Pretoria, 0184, South Africa
Branch:
Pretoria
SWIFT Code:
ABSAZAJJ
Account Name / Code:
Tradefin International
Account Number:
374181-USD-1046-10
IBAN
SELLER SETTLEMENT/TRASACTION BANK:
BANK NAME: ECO BANK UGANDA LIMITED
ACCOUNT NUMBER: 7170009076
ACCOUNT NAME: MIDASGKO MINERAL
TRADERS LTD SWIFT CODE:ECOCUGKA
BANK ADDRESS: PLOT 8A KAFU ROAD KAMPALA UGANDA
EXECUTION OF THE AGREEMENT
This agreement may be executed in any number of counterparts, and by the
parties on separate counterparts, each of which so executed and delivered will
be an original, but all the counterparts will together constitute one and same
agreement. The parties agree that all signatures to this contract are deemed
original when transmitted electronically and the contract are deemed original
copy when transmitted electronically via e-mail.
For and on behalf of the Seller:
Authorized
Signatory: Patrick M.
Company Seal:
Date: Jan. 11th-2026
For and on behalf of the Buyer:
Authorized
Signatory: Tania C.
Company Seal:
Date: Jan. 11th-2026
Buyer’s Initials
Seller’s Initials
SELLER 1 PASSPORT
Buyer’s Initials
Seller’s Initials
BUYER’S PASSPORT
Buyer’s Initials
Seller’s Initials`,
        },
      ],
      spa_images: [
        { title: "SELLER 1 PASSPORT", url: "" },
        { title: "BUYER'S PASSPORT", url: "" },
      ],
    },
  };

  const fallbackSchemas = {
    invoice: [
      { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false },
      { key: "invoice_date", label: "Invoice Date", type: "date", required: false },
      { key: "client_name", label: "Client Name", type: "text", required: true },
      { key: "client_email", label: "Client Email", type: "email", required: true },
      { key: "client_address", label: "Client Address", type: "textarea", required: false },
      { key: "destination", label: "Destination", type: "text", required: false },
      { key: "cargo_type", label: "Cargo Type", type: "text", required: true },
      { key: "currency", label: "Currency", type: "text", required: false },
      { key: "quantity", label: "Quantity", type: "number", required: false },
      { key: "unit", label: "Unit", type: "text", required: false },
      { key: "purity", label: "Purity (%)", type: "text", required: false },
      { key: "carats", label: "Carats", type: "text", required: false },
      { key: "taxable_value", label: "Taxable Value", type: "number", required: false },
      { key: "payment_wallet_address", label: "Payment Wallet Address", type: "text", required: false },
      { key: "payment_network", label: "Payment Network", type: "text", required: false },
      { key: "tax_rate", label: "Tax Rate (%)", type: "number", required: false },
      { key: "insurance_rate", label: "Insurance Rate (%)", type: "number", required: false },
      { key: "smelting_cost", label: "Smelting Cost", type: "number", required: false },
      { key: "cert_origin", label: "Certificate of Origin", type: "number", required: false },
      { key: "cert_ownership", label: "Certificate of Ownership", type: "number", required: false },
      { key: "export_permit", label: "Export Permit", type: "number", required: false },
      { key: "freight_cost", label: "Freight Cost", type: "number", required: false },
      { key: "agent_fees", label: "Agent Fees", type: "number", required: false },
      { key: "watermark_enabled", label: "Enable Watermark", type: "checkbox", required: false },
      { key: "bitcoin_enabled", label: "Enable Bitcoin QR", type: "checkbox", required: false },
    ],
    receipt: [
      { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false },
      { key: "receipt_title", label: "Receipt Title", type: "text", required: false },
      { key: "receipt_date", label: "Receipt Date", type: "date", required: false },
      { key: "client_name", label: "Client Name", type: "text", required: true },
      { key: "client_email", label: "Client Email", type: "email", required: true },
      { key: "client_address", label: "Client Address", type: "textarea", required: false },
      { key: "line_items", label: "Commodities", type: "line_items", required: false },
      { key: "currency", label: "Currency", type: "text", required: false },
      { key: "payment_method", label: "Payment Method", type: "text", required: false },
      { key: "payment_reference", label: "Payment Reference", type: "text", required: false },
      { key: "payment_wallet_address", label: "Wallet Address", type: "text", required: false },
      { key: "payment_network", label: "Network", type: "text", required: false },
      { key: "bank_name", label: "Bank Name", type: "text", required: false },
      { key: "bank_account_number", label: "Bank Account Number", type: "text", required: false },
      { key: "bank_account_name", label: "Bank Account Name", type: "text", required: false },
      { key: "bank_swift_code", label: "Bank Swift Code", type: "text", required: false },
      { key: "bank_address", label: "Bank Address", type: "text", required: false },
      { key: "company_phone", label: "Company Phone", type: "text", required: false },
      { key: "company_email", label: "Company Email", type: "email", required: false },
      { key: "company_website", label: "Company Website", type: "text", required: false },
      { key: "company_address", label: "Company Address", type: "textarea", required: false },
      { key: "notes", label: "Notes", type: "textarea", required: false },
      { key: "wet_stamp_note", label: "Wet Stamp Note", type: "text", required: false },
      { key: "watermark_enabled", label: "Enable Watermark", type: "checkbox", required: false },
      { key: "bitcoin_enabled", label: "Enable Bitcoin QR", type: "checkbox", required: false },
    ],
    skr: [
      { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false },
      { key: "skr_company_name", label: "Company Name", type: "text", required: false },
      { key: "skr_company_rc", label: "RC Number", type: "text", required: false },
      { key: "skr_license_number", label: "License Number", type: "text", required: false },
      { key: "skr_company_phone", label: "Company Phone", type: "text", required: false },
      { key: "skr_company_email", label: "Company Email", type: "email", required: false },
      { key: "skr_company_address", label: "Company Address", type: "textarea", required: false },
      { key: "depositor_name", label: "Depositor Name", type: "text", required: true },
      { key: "client_email", label: "Email Address", type: "email", required: true },
      { key: "client_address", label: "Depositor Address", type: "textarea", required: false },
      { key: "custody_type", label: "Custody Type", type: "text", required: true },
      { key: "projected_days", label: "Projected Days of Custody", type: "number", required: false },
      { key: "documented_custom_value", label: "Documented Custom Value (US$)", type: "text", required: false },
      { key: "represented_date", label: "Represented Date", type: "date", required: false },
      { key: "represented_by", label: "Represented By", type: "text", required: false },
      { key: "receiving_officer", label: "Receiving Officer", type: "text", required: false },
      { key: "watermark_enabled", label: "Enable Watermark", type: "checkbox", required: false },
      { key: "skr_watermark_enabled", label: "Enable SKR Watermark (Legacy)", type: "checkbox", required: false },
      { key: "content_description", label: "Description of Contents", type: "text", required: true },
      { key: "quantity", label: "Quantity", type: "number", required: true },
      { key: "unit", label: "Unit", type: "text", required: true },
      { key: "packages_number", label: "Number of Packages", type: "number", required: false },
      { key: "origin_of_goods", label: "Origin of Goods", type: "text", required: false },
      { key: "declared_value", label: "Declared Value (USD)", type: "number", required: true },
      { key: "deposit_type", label: "Type of Deposit", type: "text", required: true },
      { key: "total_value", label: "Total Value", type: "text", required: false },
      { key: "insurance_rate", label: "Insurance Value/Rate", type: "text", required: false },
      { key: "supporting_documents", label: "Supporting Documents", type: "textarea", required: false },
      { key: "storage_fees_label", label: "CD Storage Fees", type: "text", required: false },
      { key: "deposit_instructions", label: "Deposit Instructions", type: "textarea", required: false },
      { key: "depositor_signature", label: "Depositor's Signature", type: "textarea", required: false },
      { key: "date_label", label: "Date", type: "date", required: false },
      { key: "additional_notes", label: "Additional Information", type: "textarea", required: false },
      { key: "affidavit_text", label: "Affidavit Paragraph", type: "textarea", required: false },
      { key: "issuer_name", label: "Issuing Officer Name", type: "text", required: false },
      { key: "issuer_title", label: "Issuing Officer Title", type: "text", required: false },
      { key: "stamp_label", label: "Stamp Label", type: "text", required: false },
      { key: "bitcoin_enabled", label: "Enable Bitcoin QR", type: "checkbox", required: false },
    ],
    spa: [
      { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false },
      { key: "watermark_enabled", label: "Enable Watermark", type: "checkbox", required: false },
      { key: "watermark_url", label: "Watermark Image URL", type: "url", required: false },
      { key: "spa_tables", label: "Table Blocks (JSON)", type: "textarea", required: false },
      { key: "spa_text_walls", label: "Text Wall Blocks (JSON)", type: "textarea", required: false },
      { key: "spa_images", label: "Image Blocks (JSON)", type: "textarea", required: false },
      { key: "seller_initials", label: "Seller Initials Label", type: "text", required: false },
      { key: "buyer_initials", label: "Buyer Initials Label", type: "text", required: false },
    ],
  };
  window.CDS_DOCS_DEFAULTS = {
    payloadDefaults: payloadDefaults,
    fallbackSchemas: fallbackSchemas,
  };
})(window);
