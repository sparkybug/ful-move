# Requirements coverage

Source: FUL_Campus_Shuttle_Booking_Requirements.pdf, dated 5 October 2026.

| Requirement | Implementation |
| --- | --- |
| Public boarding list and bus detail | BoardController, board/run Blade pages, 12-second polling, terminal filter |
| Student authentication, booking and ticket | AuthController, StudentController, student views |
| Driver bus registration / admin approval (D1, A1) | DriverController::registerBus, AdminController::approve |
| Boarding run at configured terminal (D2) | Driver dashboard presence confirmation; ShuttleService::openRun validates approved ownership, route/fare, terminals, one active driver/bus |
| Estimate and walk-in updates (D3) | ShuttleService::updateRun; run lock and capacity checks; explicit estimate timestamp and overdue display |
| Passenger check-in (D4) | ShuttleService::board; status-only change, no second seat or charge |
| Depart, arrive, cancel (D5) | ShuttleService::transition; server-enforced state machine; cancellation refunds all paid tickets including boarded ones |
| Driver wallet (D6) | Shared wallet view with booking/run references and credit/refund entries |
| Terminals and fares (A1) | Admin settings; route + bus type fare uniqueness and immutable run snapshots |
| Search students, issue credit (A2) | Admin students page; reason, actor ID, operation key, ledger |
| Operational overview (A3) | Admin overview: boarding, departed, online counts, walk-ins, cancellations, remaining capacity |
| Date range totals and driver earnings (A4) | Admin reports and linked transfer ledger; online revenue less reversals |
| Seat/wallet integrity | Central transactional service; student/run/wallet locks; database uniqueness; integer kobo; nonnegative service checks |
| Same-request retries | Scoped booking/credit operation keys; deterministic refund operation key; original refund transfer uniqueness |
| Seed four capacities | DatabaseSeeder: 60, 18, 12, 10; four distinct drivers |
| Test gate | Feature acceptance suite plus independent-process MySQL concurrency races |
| Deployment | Production configuration and admin-creation command provided; external hosting not configured |
| Two-phone rehearsal | Instructions supplied; physical devices not available in this workspace |

## Decisions and assumptions

- Laravel 12 matches the existing PHP 8.2 runtime; dependencies are pinned by Composer and pnpm lockfiles.
- Felele Campus and Adankolo Campus are editable sample terminal names. Every seeded fare is ₦200; actual group values can be configured by an admin.
- Public registration allows student or driver accounts. Admin accounts can only be created through the terminal.
- A rejected bus can be approved later. A bus with an active run cannot be rejected until that run finishes or is cancelled.
- Only a run's driver can check in, depart, arrive, or update it. Admins may cancel boarding runs.
- A student may book another bus after their earlier bus departs, consistent with the requirement limiting exclusivity to boarding runs.
- Reports use transaction timestamps in Africa/Lagos (WAT). A date range can have negative net online revenue if its refunds reverse bookings from an earlier period.
- Repeated cancellation is a successful no-op after the first refund. Reusing a booking/credit key for a different payload is rejected.
- There is no mechanism to delete ledger history or withdraw driver credits.

