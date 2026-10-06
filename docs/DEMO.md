# FUL Move presentation guide

## Before presenting

Start the project with `scripts/Start-Demo.ps1` (prepared Windows checkout) or `php artisan serve`. Keep one admin, one driver, and one student browser session separate.

Accounts: `admin@ful.test`, `driver18@ful.test`, `student@ful.test`. Password for each: `Campus@2026`.

The student initially has ₦1,000. The prepared board has sample runs; driver18 can manage the existing FUL-018 run. For a demonstration that includes opening a run, cancel the existing boarding run first, then open another. Cancellation preserves history and refunds active online bookings.

If the estimate is in the past, the driver should update it. This is part of the required behavior.

## Walkthrough

1. **Admin:** show the approved buses: 60, 18, 12, and 10 seats. Show the two terminal names and ₦200 fares in both directions.
2. **Admin:** open Student credits. The student already has a recorded initial ₦1,000 credit. Show it in the ledger. To demonstrate issuing a fresh ₦1,000 from zero, use a newly registered student instead of adding another ₦1,000 to the seeded account.
3. **Driver:** open boarding for FUL-018, choose its route and departure estimate, and confirm physical presence at the terminal. The new run has 18 seats.
4. **Student:** find the bus on the public board, open the details, tick fare confirmation, and choose Reserve my seat. The live confirmation shows the ₦200 fare and ₦800 remaining balance. Confirm.
5. **Student:** show the ticket and booking code. The bus now has 17 seats. The student wallet has ₦800; driver wallet has ₦200. Both ledgers link to the same transfer.
6. **Driver:** set the total walk-in count to 3. Availability becomes 14. Do not include online passengers in that count.
7. **Driver:** update the departure estimate. The public list reflects it within 12 seconds.
8. **Student:** cancel the unchecked ticket. Availability becomes 15 (18 minus 3 walk-ins). Student balance returns to ₦1,000 and driver balance to ₦0. Show the original transfer and separate refund.
9. **Student:** book again. **Driver:** mark the booking boarded. The seat count does not decrease again.
10. **Driver:** close boarding and depart. The bus leaves the public list. The student ticket remains visible, but cannot be self-cancelled.
11. **Driver:** mark arrived. A new run can now be opened.
12. **Admin:** show the run figures, online booking revenue, refunds, driver net earnings, and transfer ledger.

## Explain the scope

This is a responsive campus-loading-point website. It does not track GPS or offer off-terminal pickups. The driver provides departure estimates. The wallet represents internal demonstration credits only; walk-in cash is not recorded as wallet revenue.

## Two-phone presentation

Use a deployed HTTPS instance with distinct accounts on each phone. For a private local-network rehearsal, serve on your computer's LAN interface with `php artisan serve --host=0.0.0.0 --port=8000`, connect both devices to the same trusted Wi-Fi, and use the computer's LAN IP. Windows may ask you to allow private-network access. Do not expose the PHP development server to the public Internet.

