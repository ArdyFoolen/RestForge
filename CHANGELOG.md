# Changelog

All notable changes to this project will be documented in this file.

## [1.2.3] - 2026-07-17

### Added

* Added refresh password for admins to change the password.

### Changed

* Not allowed to disable one self.
* Always create an enabled user.
* Allow users to change their own password.

### Fixed

* Only allow update of password through password route.

## [1.2.2] - 2026-07-16

### Changed

* Changed route of Version to be in api/v1.

## [1.2.1] - 2026-07-12

### Added

* Added session to storage in dashboard.
* Updated README.md

## [1.2.0] - 2026-07-12

### Added

* Added refresh token authentication with rotating refresh tokens.
* Added session-based authentication model.
* Added refresh token reuse detection to help identify compromised sessions.
* Added automatic session invalidation when refresh token reuse is detected.
* Added refresh token expiration.
* Added refresh token hashing before storage.
* Added `POST /auth/refresh` endpoint.
* Added `POST /auth/logout` endpoint.
* Added session management endpoints:

  * `GET /sessions`
  * `GET /sessions/{id}`
  * `PUT /sessions/{id}`
  * `DELETE /sessions/{id}`

### Changed

* Refresh tokens are now rotated on every successful refresh.
* Session management now supports refresh token families.
* `Response::success()` now accepts an optional HTTP status code.

### Fixed

* Improved authentication and session validation during token refresh.
* Improved handling of revoked and expired refresh tokens.

## [1.1.3] - 2026-07-05

### Fixed
* Check in Dashboard if directory exists.

## [1.1.2] - 2026-07-05

### Fixed
* Use same caps for folder names.

## [1.1.1] - 2026-07-02

### Fixed
* Use DIRECTORY_SEPARATOR to correctly use path names.

## [1.1.0] - 2026-07-02

### Changed
* Removed the public folder from the project structure.
* Dashboard now calculates system storage correctly for the new layout.

### Fixed
* Correct system size calculation by combining the project root (non-recursive) with the 'src' directory (recursively).

## [1.0.5] - 2026-07-02

### Changed
* Changed Version.

## [1.0.4] - 2026-07-02

### Changed
* Changed total storage to 1GB.

## [1.0.3] - 2026-07-02

### Added
* Redirect to HTTPS.

## [1.0.2] - 2026-07-02

### Added
* Initial CHANGELOG documentation.

## [1.0.1] - 2026-07-02

### Added
* Initial README documentation.
* Installation instructions.
* Query language documentation.
* Authentication and authorization documentation.

## [1.0.0] - 2026-07-02

### Added
* Initial public release.
* Routing.
* Middleware.
* JWT authentication.
* Role-based authorization.
* User management.
* JSON storage.
* CRUD operations.
* Validation and sanitization.
* Query language.
* Sorting and pagination.
* Dashboard.
* Logging.
* HTML API documentation.
