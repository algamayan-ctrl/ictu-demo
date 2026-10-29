# SPMU-ACPMP User Guide

## Access rules

- Only Borrower accounts may create borrowing requests.
- Active staff classifications are SPMU Admin / Head and SPMU Action Officer. ICTU Maintainer is retired and cannot sign in.
- SPMU accounts cannot borrow.
- GSU and VPAF are not system accounts/portals/approvers. They are physical institutional signatories on the printed Borrowing Request Letter when required.
- No electronic signature is used. Required signatures are handwritten/wet signatures on printed documents; accomplished scans/evidence are uploaded and verified.
- A formal SPMU delegation gives only the recorded decision authority and uses the delegate's own account.
- Nobody may approve/release/receive their own borrowing transaction.

## Borrower

1. Keep account/contact details current.
2. Check Available Items and Borrowing Calendar for the required dates.
3. Create a request and select quantities/use location.
4. Save the Draft. The system generates a printable Borrowing Request Letter.
5. Print the letter and obtain the required handwritten/wet signatures from the physical institutional signatories, including GSU/VPAF where shown on the form.
6. Scan and upload the fully signed Borrowing Request Letter. Upload Permission to Conduct when the request represents a student activity/organization.
7. Submit to SPMU and monitor status. On-campus requests go directly to the SPMU Head; off-campus requests first go to the Action Officer for verification.
8. If the assigned reviewer returns the request, correct the request/document and resubmit. The corrected request follows its current on-campus or off-campus routing. For off-campus requests, VERIFIED still waits for the separate Head decision.
9. After Head approval, view/download the generated Borrower Slip and applicable Gate Pass, then bring them to SPMU on the scheduled pickup date.
10. Track custody, return deadlines, Early Return and accountability records from the borrower portal.

## SPMU Admin / Head

- Review on-campus submissions directly and off-campus requests already marked VERIFIED by the SPMU Action Officer.
- Check request details, required supporting documents, inventory availability and approved quantity. On-campus review includes the completeness and availability checks because there is no prior Action Officer verification stage.
- Choose Approve, Return for Revision, or Reject.
- Approval reserves the exact approved quantity; there is no downstream GSU/VPAF system approval.
- Oversee cancellation, accountability, reports and other Head-authorized controls.
- A formally delegated SPMU Action Officer may make the recorded SPMU decision under their own identity.

## SPMU Action Officer

- Verify submitted off-campus requests and their required supporting documents; return incomplete requests for correction. On-campus submissions go directly to the Head.
- Mark complete off-campus requests VERIFIED and route them to the Head. Verification is not approval and creates no reservation.
- Schedule the pickup window after approval.
- Prepare exactly the approved/reserved quantity; do not silently reduce an approved quantity.
- Validate the automatically generated Borrower Slip and applicable Gate Pass presented by the borrower.
- Confirm required physical handover requirements before release; do not create a second Gate Pass.
- Record physical release, returns, conditions, evidence, incidents, billing/accountability and applicable Gate Pass/Laundry evidence.
- Open **Laundry Operations** to record borrower turnover and physical processing for linen jobs.
- Continue cleaned linen through the existing SPMU Return & Inspection flow and archive the accomplished Laundry Form after final acceptance.

### Borrower's Slip preview

Before release, Preview opens the pickup copy for printing and bringing to SPMU. After SPMU records release, the same Preview opens the slip with the actual release date/time directly. The retained original pickup PDF is available to the Admin / Head under **Request Records → Document History**.

Use the same printed Borrower's Slip at pickup and return. At pickup, the actual issuer and borrower sign Issued by / Received by on that paper. The borrower brings that same slip when returning the items; the actual returner and responsible receiving staff sign the applicable return acknowledgement blocks on the same paper. Verified by is signed only after the corresponding verification. The borrower does not need to sign in at the SPMU counter. SAO records the operational actions using their own account. No second printout or upload of an accomplished Borrower's Slip is required.

The digital slip shows one Date Returned and recorded item-condition remarks only after all required return records and, where applicable, accomplished Laundry Form processing are complete. Those fields remain blank beforehand; the slip does not print a Pending label. The existing required accomplished Laundry Form upload is retained and is separate from the Borrower's Slip. SAO can open **Return → transaction → Return Documents → Borrower Slip → Preview** to review the recorded values. The borrower can view their own updated record later without needing to be present or signed in when SAO finishes processing. Physical signatures stay on the printed slip and do not appear automatically in the generated PDF. The existing linen/non-linen sections on the Action Officer return screen remain in place.

## ICTU (Technical System Custodian)

- ICTU has no application account. The SPMU Admin / Head manages user accounts under **User Accounts** and operational settings under **Configuration**.
- ICTU maintains hosting, Cloudflare, deployment, the database and backups, source code, and technical troubleshooting. See "Technical turnover and responsibilities" in [DEPLOYMENT-ICTU.md](DEPLOYMENT-ICTU.md).
- Retired GSU/VPAF records are hidden from the active User Accounts list and cannot be assigned.
- ICTU does not approve borrowing requests or perform SPMU custody work.

## Status principles

- Draft: editable; no inventory reservation.
- Under SPMU: on-campus requests await the Head decision directly; off-campus requests await Action Officer verification and then the separate Head decision. No reservation exists yet.
- Approved Ready for Release: SPMU approved and exact quantity is reserved.
- Preparing for Release: pickup/custody record exists; SPMU Action Officer schedules/prepares items.
- Released/Active: physically issued quantity is in borrower custody.
- Fine ordinary item return: Available after SPMU inspection.
- Fine linen return: routes through Laundry and returns to Available only after Laundry + final SPMU completion.
- Early Return is a notice until SPMU physically receives/inspects the property.
