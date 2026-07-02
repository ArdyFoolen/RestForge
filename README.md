# RestForge

A lightweight REST API framework written in PHP that provides authentication, authorization, validation, filtering, JSON storage, and a clean routing system without requiring a database.

---

# Features

- RESTful routing
- JWT authentication
- Role-based authorization
- User management
- Automatic owner account bootstrap
- Password hashing
- Change password support
- JSON file storage
- CRUD operations
- Request validation and sanitization
- Powerful query language
- Sorting
- Pagination
- Nested object filtering
- Array filtering
- Date filtering
- Dashboard endpoint
- Log management
- Global exception handling
- HTML API documentation
- Middleware support
- API versioning (`/api/v1`)

---

# Requirements

- PHP 8.2+
- Apache (XAMPP recommended)
- OpenSSL extension
- JSON extension

---

# Installation

Clone the repository:

```bash
git clone https://github.com/ArdyFoolen/RestForge.git
```

Configure your web server so that the `public` directory is the document root.

Make sure the `Storage` directory is writable.

---

# Default Owner Account

When the framework starts and no owner account exists, it automatically creates one using the credentials configured in `Config.php`.

If the last owner is deleted, the default owner is recreated automatically.

---

# Authentication

Login:

```http
POST /api/v1/login
```

Use the returned JWT:

```http
Authorization: Bearer <token>
```

---

# Authorization

Current roles:

- owner
- admin
- user
- logreader
- logdeleter

Permissions are assigned to roles and checked through middleware.

---

# Query Language

## Equality

```text
?name=John
```

## Comparison

```text
?price[gt]=100
?price[ge]=100
?price[lt]=500
?price[le]=500
```

## String Operators

```text
?name[contains]=john
?name[startsWith]=jo
?name[endsWith]=son
```

All string comparisons are case-insensitive.

## IN Operator

```text
?roles=admin,user
```

## Nested Objects

```text
?customer[city]=London
```

## Arrays

```text
?tags=[electronics]
```

## Date Filtering

Use ISO 8601 UTC dates.

```text
?created_at[gt]=2026-06-30T08:40:53Z
```

The `Z` suffix is recommended instead of `+00:00` to avoid URL encoding issues.

---

# Sorting

Ascending:

```text
?orderby=name
```

Descending:

```text
?orderby=created_at&descending=true
```

---

# Pagination

```text
?offset=0&limit=25
```

---

# Built-in Endpoints

- Login
- Change Password
- Who Am I
- Users
- Items
- Dashboard
- Logs
- Version

---

# Project Structure

```
public/

resources/
    index.html

src/
    Controllers/
    Middleware/
    Routing/
    Security/
    Storage/
    Validation/

Storage/
    Users/
    Items/
    Logs/
```

---

# Version

Current version:

```
v1.0.0
```

---

# License

MIT License.

Feel free to use, modify and distribute this project under the terms of the MIT License.