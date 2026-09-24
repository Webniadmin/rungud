# InZENtive Back Office — Analysis, Decisions and Open Questions

**Companion to the Lovable Build Brief · Version 1.0**

---

## 1. What the two emails actually describe

Gudrun's two messages read as a list of wishes. They are not. They describe one workflow with one
gate in the middle:

```
Registration → Verification → Price / Discount → [HUMAN GATE] → Invoice → Payment
    → Participation → Education → Certificate
```

Everything before the gate should be automatic. Everything after it should be automatic. The gate
itself — Gudrun's final approval — must stay manual, permanently and by design. Most of the pain in
the current process comes from the fact that the automatic parts are also manual today.

A second, quieter requirement runs underneath: **Robert should stop being a bottleneck for
information, and Gudrun should stop being a bottleneck for questions that are not accounting
questions.** Her own sentence is the clearest statement of the brief: *general event or participant
enquiries should not unnecessarily have to be routed through accounting.*

---

## 2. The one thing that was not in the emails but should drive the build

From the monetisation strategy already in this project:

> Three hundred members at €200 should be €60,000 a year. The account shows €11,500. The cause is
> operational, not commercial: memberships are opened manually to verify instructor status, and they
> never close.

That is roughly **€48,000 a year lost to a missing scheduled job.** No pricing decision in the
strategy document comes close to it in value, and the same document says so explicitly.

Consequence for this build: the Classroom membership lifecycle — activation, expiry date, automatic
expiry, renewal reminders, visible expiry dashboard — is **Phase 1, not a later nice-to-have.** It is
also the single clearest way to demonstrate the system's value to Robert within the first month.

---

## 3. Decisions taken in the brief, and why

### 3.1 This system is the source of truth; WordPress presents

WordPress keeps the brand, the content and the public experience. Events, seats, registrations,
people, memberships and invoices live here. WordPress reads from a public API and links to a
registration form served by this application.

The alternative — a registration form built in WordPress that pushes data across — requires a second
implementation of seat counting, the Classroom check, prerequisite checking and billing rules. Two
implementations of the same logic diverge within weeks, and the divergence shows up as a
double-booked course or a wrong discount, which is exactly the class of problem this project exists
to remove.

### 3.2 Robert gets one write action, not zero

The second email says Robert needs read access. The first email says Robert grants individual
discounts. Both are true. The resolution: Robert can *record* a discount with a reason, which
appears on Gudrun's draft with his name and the date. He cannot approve or send anything financial.

This is worth more than it looks. Today those discounts live in WhatsApp and email and get
reconstructed weeks later during invoicing. Capturing them at the moment of agreement removes an
entire recurring category of error.

### 3.3 Participant and billing recipient are separate objects — not two modes of one object

Gudrun flagged this as very important, and she is right. Modelling the billing recipient as an
optional set of fields on the participant breaks the moment one company pays for four people with
four different membership statuses. The brief models them as separate tables with a many-to-many
link, and the Classroom check is bound to the participant by construction — it is not possible to
accidentally check the company's membership, because companies do not have one.

### 3.4 Snapshots, not live lookups, on anything financial

An approved invoice freezes the billing address, the participant name, the prices and the tax
treatment. If a company moves offices in June, the invoice issued in March must still show the March
address. Similarly, the Classroom status is snapshotted onto the registration at booking time, so a
membership that expires between booking and invoicing does not silently revoke a discount that was
correctly granted.

### 3.5 Approved invoices are immutable

Corrections go through cancellation, credit note, and a new invoice. This is not a design preference;
it is what German GoBD rules require of a system that produces invoices. Building editable invoices
now and retrofitting immutability later means rewriting the financial core.

### 3.6 The interface is German

Robert and Gudrun work in German. An English-first interface with a German toggle produces an
English-first mental model and English error messages at the worst moments. German is the default;
English is available for you and for any future team member.

### 3.7 A training mode, built in

A sandbox with demo data where nothing is real and no email leaves. It is what the joint training
session runs on, and what both of them return to when they are unsure whether an action is
reversible. For two users with low technical confidence, this is probably the highest-leverage
usability feature in the entire system — fear of breaking something is the main reason non-technical
users avoid software they have been given.

---

## 4. Risks worth naming now

| Risk | Why it matters | Recommended handling |
|---|---|---|
| **Legal invoice compliance** | Issuing invoices from a custom system pulls GoBD — immutability, audit trail, ten-year retention, verifiable export — into scope. | Either build it properly from day one (the brief does), or keep this system operational and issue through lexoffice/sevdesk/bexio via API. Decide with the accountant **before** Phase B4. |
| **German e-invoicing (ZUGFeRD / XRechnung)** | Mandatory receipt since Jan 2025; issuance obligations phase in from 2027. Educations invoiced to German companies are in scope. | Plan the PDF/A-3 hybrid output now rather than retrofitting. |
| **Two countries, two VAT realities** | DE 19% and CH 8.1% imply either two entities or one entity with a Swiss VAT registration. Each implies separate invoice number ranges. | Confirm the legal structure before building the numbering. |
| **Online sales across borders** | Memberships and online programmes follow destination-country VAT in the EU (OSS), not the live-event rule. | Treat as a separate tax profile family. Do not conflate with the live-event logic. |
| **Data migration** | Duplicate people, half-known membership end dates, historical educations with no record. | Build the forgiving CSV importer in Phase B1 and budget real time for cleaning. Expect surprises. |
| **GDPR** | Personal data of EU and Swiss citizens, plus marketing consent. | Supabase EU region, double opt-in for marketing, retention policy per data type, a DPA with every processor. |
| **Deliverability** | If a campaign burns the sending domain, invoices stop arriving. | Separate subdomain for campaigns; transactional mail on its own. |
| **Adoption** | The best-built system fails if Robert reverts to calling Gudrun. | Training mode, a joint session, and a first month where the system visibly answers his questions faster than a phone call does. |
| **Scope creep in Lovable** | Nine phases is already a lot. | Run them strictly in order. Resist "while we're here" features until B4 is stable. |

---

## 5. Questions for Robert and Gudrun

Grouped so they can be answered in one sitting. Answers to §5.1 and §5.2 block the financial build.

### 5.1 Legal and financial structure — blocking

1. Which legal entity issues invoices? One, or a German and a Swiss entity?
2. Should invoices be issued from this new system, or should the system prepare them and push them
   into existing accounting software? Which software does the accountant use today?
3. What is the current invoice number format, and what is the last number used in each series?
4. Standard payment terms — 14 days? Different for companies and private individuals?
5. Which currencies, and how is the EUR/CHF decision made — by event location, or by customer?
6. What exactly does the accountant need exported, and in which format?

### 5.2 Discounts and prices — blocking

7. What is the Classroom discount, precisely? The strategy document mentions 20–30% off events and
   specific examples (Miami €550 vs €900, convention €117 vs €300). Is it a fixed percentage, or a
   per-event member price?
8. Which date governs the discount: registration date, payment date, or event date?
9. Does an individual discount replace the Classroom discount, or stack on top of it?
10. Are there early-bird prices, and are they automatic or ad hoc?
11. Are there promotional codes today? Should there be?

### 5.3 Events and operations

12. What is a realistic list of course types with their default prices, durations and prerequisites?
13. What are the current cancellation and refund terms? Deadlines, fees, credit for a later course?
14. Should the website show exact remaining seats, or only "a few places left"?
15. Should the waiting list be visible to the public, with a position number?
16. How long should a waiting-list offer stay open before it passes on — 24, 48, 72 hours?
17. Who are the trainers who need to appear on events, and will they eventually need their own
    login and participant list?

### 5.4 Memberships

18. What are the current membership types and prices, after the change to €200 plus €50 for the
    combination tier?
19. How is instructor status verified today, and can any part of that become automatic?
20. What happens to existing members at migration — honour the remaining term, then renew?
21. Renewal: automatic via Stripe, or invoice and manual payment?
22. What happens on the day a membership expires: does access stop immediately, or is there a grace
    period?

### 5.5 Certificates and education

23. Which courses issue certificates, and what exactly does the certificate need to state?
24. Is there a numbering scheme for certificates, and does it need to be continuous?
25. How far back should historical education records be entered, and where do they exist today?
26. Do certificates ever expire or require refreshing?

### 5.6 Communication

27. Which emails go out today, manually, that should become automatic?
28. Which email address should invoices come from, and which should campaigns come from?
29. Does a newsletter exist today, on which platform, and should it be migrated or left in place?
30. Robert's personal notes — would he actually use this, and would he write them himself or approve
    a draft?

### 5.7 Access and training

31. Does anyone else need access besides Robert, Gudrun and Webni — now or within a year?
32. Does Robert want a participant list on his phone during an event, with attendance ticking?
33. When should the joint training session happen relative to go-live? Recommendation: one session
    two weeks before, on training mode with real data imported, and a second short one a week after.

---

## 6. Suggested sequence

| Phase | Content | Rough duration | Gate |
|---|---|---|---|
| 0 | Answers to §5.1 and §5.2; accountant conversation | 1 week | Legal structure confirmed |
| 1 | B0 + B1 — shell, people, billing, memberships, import | 2 weeks | Membership expiry runs automatically |
| 2 | B2 + B3 — events, seats, waiting list, registrations, discounts | 2–3 weeks | C1, C2 and C4 pass |
| 3 | B4 — invoices, approval, VAT, payments, exports | 2–3 weeks | Accountant signs off on a test invoice |
| 4 | B5 — certificates, education history, fact sheets | 1–2 weeks | C8 passes |
| 5 | B7 — website integration, public registration form | 2 weeks | A real registration flows end to end |
| 6 | B6 + B8 — communication, campaigns, reports | 2 weeks | — |
| 7 | B9 + training + migration + go-live | 2 weeks | Robert passes C7 unaided |

Phases 5 and 6 can be reordered if the website go-live date demands it. Phases 1 to 3 cannot be
reordered — each depends on the one before it.
