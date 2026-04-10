 ## 📌 System Approved: Locker Management System

## 📌 System Description
This system is designed to manage locker assignments efficiently. It allows administrators to assign, monitor, and manage lockers for students. The system helps track available, occupied, and maintenance lockers while improving organization and reducing manual work.

---
## 📌 System Information
**System Title:** Locker Management System  

**Group 5:**

**Leader**: RG A. Minoza  
**Member**: 
- Lucky Anjay Dela Cruz
- Krisden Agir
- Bien Felipe Abueva


## 📌 System Flow (Overview)

## **1. Admin logs into the system**
- System authenticates credentials using the users table  
- On success, a session is started and the admin is redirected to the dashboard  

## **2. System displays dashboard overview**
- Shows total students and total lockers  
- Displays locker status counts:
  - Available  
  - Occupied  
  - Maintenance  
- Shows the latest active assignments  

## **3. Admin manages students**
- Add, edit, or delete student records  
- System prevents deletion if the student has an active locker assignment  

## **4. Admin manages lockers and buildings**
- Add, edit, or update lockers and building information  

## **5. Admin assigns a locker**
- Selects a student and an available locker  
- System shows a confirmation prompt before saving  

## **6. System processes assignment**
- Locker status updates:
  - Available → Occupied  
- Assignment record is saved with the date assigned  

## **7. Admin processes locker return**
- System records the return date  
- Locker status updates:
  - Occupied → Available  

## **8. Admin manages maintenance**
- Marks available lockers as under maintenance with a reason  
- Locker becomes unavailable for assignment  
- After restoration, locker becomes available again  

## **9. Admin views reports**
- Available lockers  
- Occupied lockers with student details  
- Full assignment history with return status  

## **10. Admin logs out of the system**
- Session is destroyed  
- System redirects to login page  

## 📌 Flowchart 
<img width="1920" height="1431" alt="FLOWCHART drawio" src="https://github.com/user-attachments/assets/27486e7f-291d-4712-81b5-4126673ecec1" />

