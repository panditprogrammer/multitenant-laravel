📘 SMART LIBRARY MANAGEMENT SaaS
(Final Feature Specification – Updated)
🧠 1. PRODUCT OVERVIEW

A multi-tenant Smart Library Management System where:

Each library runs independently (separate DB + subdomain)
Library owners manage full operations
Students interact with system (login + attendance)
🏗️ 2. ARCHITECTURE
🌐 Central App
Owner authentication (Laravel built-in)
Create/manage libraries (tenants)
🏬 Tenant App

Each library contains:

Students
Seats
Memberships
Payments
Attendance
Expenses
Reports
👥 3. USER ROLES
🧑‍💼 Library Owner
Full control of library system
🎓 Student (NEW ✅)
Has login access
Can:
View membership
View seat & time
View payment history
Scan QR for attendance
🔐 4. AUTHENTICATION
Central:
Owners login (already done)
Tenant:
Students login (NEW)

👉 Separate auth per tenant DB

💺 5. SEAT MANAGEMENT
Features:
Bulk seat generation
Floor/section support
Seat types (NEW ✅):
AC
Non-AC
Premium (optional)
Example:
A1 (AC)
A2 (Non-AC)
B1 (AC)
📋 6. MEMBERSHIP SYSTEM (CORE)
Membership includes:
Student
Seat
Date range
Fixed time range
Example:
Ravi → A1 → 1 Jan–30 Jan → 8–11
Rules:
Fixed seat
Fixed time
No overlap allowed
⏰ 7. TIME + AVAILABILITY LOGIC

Seat unavailable if:

Same seat
Date overlap
Time overlap
💰 8. PRICING SYSTEM
Hour-based pricing:
Same rate for all seats (for now)
Future flexibility:
Different pricing by seat type (AC/Non-AC)
💳 9. PAYMENT SYSTEM
Payment Methods:
Cash
Razorpay (online)
⚡ Razorpay Auto Verification (NEW ✅)
Features:
Auto verify payment using:
payment_id
signature
No manual confirmation needed
Update status automatically
Flow:
User pays → Razorpay → Callback → Verify → Save payment
🧾 10. INVOICE SYSTEM
Features:
Generate PDF invoice after payment
Unique invoice number
Includes:
Student details
Seat
Time
Amount
Payment method
📷 11. QR CODE ATTENDANCE
Features:
Each student has QR code
Scan to mark:
Check-in
Check-out
Data:
student_id
seat_id
date
check_in
check_out
📊 12. DASHBOARD
Shows:
Total seats
Occupied seats (current time)
Active memberships
Today’s revenue
Attendance summary
Pending payments
📈 13. REPORTS & ANALYTICS (NEW ✅)
Owner can view:
📊 Revenue Reports
Daily / Monthly earnings
💺 Seat Utilization
% occupancy
Most used seats
👥 Student Reports
Active students
Expired memberships
⏰ Time Slot Analysis
Peak hours
Low usage time
📉 Expense Reports
Monthly expenses
Profit estimation
🔔 14. PAYMENT REMINDERS
Features:
Detect:
Due payments
Expiring memberships
Future:
SMS / WhatsApp reminders
💸 15. EXPENSE MANAGEMENT
Features:
Add expense
Categories:
Rent
Electricity
Salary
Reports
🧠 16. BUSINESS RULES
Seat Rules:
Reusable by time
No overlap
Membership Rules:
Fixed time & seat
Date range based
Payment Rules:
Must be recorded
Auto-verified for online
Attendance Rules:
QR-based
Check-in + check-out
🧱 17. DATA STRUCTURE OVERVIEW
Central DB:
users (owners)
tenants
Tenant DB:
users (students)
seats
memberships
payments
attendance
expenses
🔥 18. SYSTEM TYPE

👉 Time-based subscription system with analytics & automation