# Event Registration Module (FOSSEE Drupal Task)

## Overview

The Event Registration module is a custom Drupal 10 module developed as part of the FOSSEE Drupal task.  
It allows administrators to configure event details and enables users to register for an event within a specified registration period.

The module is implemented using Drupal Form API, custom database tables, validation logic, and email notifications.

---

## Installation Steps

1. Copy the module folder to your Drupal installation:

```
modules/custom/event_registration
```

2. Import the database tables using the provided SQL file:

```bash
mysql -u username -p database_name < event_registration_tables.sql
```

3. Enable the module from the admin interface:

```
Admin → Extend → Event Registration
```

4. Clear cache:

```bash
drush cr
```

---

## URLs

### Event Configuration Page (Admin)

```
http://fossee-drupal-task.ddev.site/admin/config/event-registration
```

Used by administrators to configure:
- Event name  
- Event category  
- Event date  
- Registration start date  
- Registration end date  

**Access:** Administrator only

---

### Event Registration Form (User)

```
http://fossee-drupal-task.ddev.site/register-event
```

Allows users to register for the event.  
The form is accessible only between the configured registration start and end dates.

**Access:** All users

---

### Admin Registration Listing Page

```
http://fossee-drupal-task.ddev.site/admin/event-registrations
```

Displays a list of all event registrations submitted by users.

**Access:** Administrator only

---

## Database Tables

### event_registration_config

Stores event configuration details entered by the administrator.

**Columns:**
- `id` – Primary key  
- `event_name` – Name of the event  
- `event_category` – Category of the event (Online Workshop, Hackathon, Conference, One-day Workshop)  
- `event_date` – Event date (timestamp)  
- `registration_start` – Registration start date (timestamp)  
- `registration_end` – Registration end date (timestamp)  

---

### event_registration_entries

Stores registration details submitted by users.

**Columns:**
- `id` – Primary key  
- `full_name` – Participant name  
- `email` – Participant email address  
- `college` – College name  
- `department` – Department name  
- `created` – Registration timestamp  

---

## Validation Logic

The following validations are implemented:
- All form fields are mandatory  
- Email field is validated for correct format  
- Registration form is accessible only within the configured registration period  
- Duplicate registrations using the same email address are prevented  

---

## Email Logic

- After successful registration:
  - A confirmation email is sent to the registered user  
  - A notification email is sent to the administrator  
- Emails are sent only after successful validation and database insertion  

---

## Module Purpose

This module demonstrates:
- Drupal Form API usage  
- Custom routing and permissions  
- Custom database table handling  
- Form validation and conditional access  
- Email notification handling  

---

## Author

Developed by **Aashi** as part of the **FOSSEE Drupal Internship Task**.
