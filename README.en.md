# ☕ BIJI — Blockchain Integrated Journey Intelligence

Pilih Bahasa / Choose Language:
[Bahasa Indonesia](README.md) | [English](README.en.md)
---
> A specialty coffee supply chain management platform for Indonesia, with a focus on **Sulawesi** coffee, powered by blockchain technology to guarantee the authenticity and transparency of every batch from farm to buyer.

---

## 📖 Table of Contents

- [About the Project](#-about-the-project)
- [Who Are the Users?](#-who-are-the-users)
- [Key Features](#-key-features)
- [System Workflow](#-system-workflow)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Running Locally](#-running-locally)
- [Environment Variables](#-environment-variables)
- [API Overview](#-api-overview)
- [Database](#-database)
- [Blockchain & Smart Contract](#-blockchain--smart-contract)
- [Design & UI](#-design--ui)
- [Running Tests](#-running-tests)
- [Contributing](#-contributing)

---

## 🌱 About the Project

**BIJI** is a web-based system that connects three parties in the coffee supply chain:

1. **Farmer** — records and monitors their harvest batches
2. **Exporter** — acquires harvest batches, issues digital certificates, and markets them
3. **Buyer** — purchases certified coffee batches and verifies their authenticity

What sets BIJI apart from ordinary management systems is its use of the **Polygon blockchain** as a "digital notary". Every certificate issued by an exporter is registered on the blockchain network, allowing buyers to cryptographically prove that the document is genuine and has never been tampered with — without needing to trust any third party.

The system is designed for a **closed** ecosystem — all actors must be registered and authenticated. No data can be accessed publicly without logging in.

---

## 👥 Who Are the Users?

| Role | Primary Access | Device |
|---|---|---|
| **Farmer** | Record harvest batches, input daily logs (temperature & humidity), mark batches as export-ready | Mobile / Phone (designed offline-first) |
| **Exporter** | View & acquire batches from farmers, issue digital certificates to the blockchain, release batches for sale | Desktop / Tablet |
| **Buyer** | Browse the marketplace, purchase certified batches, verify authenticity via QR code or hash | Desktop & Mobile |

Each role has its own **unique color theme** automatically applied after login:
- 🌿 **Farmer** → Leaf green
- ☕ **Exporter** → Coffee brown
- ✨ **Buyer** → Gold

---

## ✨ Key Features

### For Farmers
- ✅ Register new harvest batches (variety, farm GPS, elevation, harvest date)
- ✅ Input daily temperature & humidity logs with an interactive slider
- ✅ View the status of all own batches
- ✅ Change batch status to "export-ready"
- ✅ **Offline mode** — data can still be entered without internet and will automatically sync when connection is restored

### For Exporters
- ✅ View the list of farmer batches ready to be acquired
- ✅ Data health warning indicators for logs (if temperature/humidity is abnormal)
- ✅ Acquire a batch with a single click (guaranteed no double-claim)
- ✅ Issue digital certificates: generate PDF → compute SHA-256 → register on the Polygon blockchain
- ✅ Auto-retry if blockchain registration fails (max. 3 times, with increasing intervals)
- ✅ Release batches to the marketplace by setting a sale price

### For Buyers
- ✅ Browse the marketplace of certified coffee batches
- ✅ View farmer profiles, production data, and post-harvest log charts per batch
- ✅ Purchase a batch through a simple 3-step flow
- ✅ Verify certificate authenticity via **QR code scan** or **manual hash input**
- ✅ Download the certificate PDF and view the transaction proof on Polygon Explorer
- ✅ Manage the collection of purchased batches

### For All Users
- ✅ Multi-device session management (view & logout from other devices)
- ✅ Personal preferences (temperature unit, elevation, address, etc.)
- ✅ Real-time activity status notifications

---

## 🔄 System Workflow

Here is the complete flow from farm to buyer:

```
[FARMER]
  1. Register a new harvest batch
     → System generates a unique internal code
  2. Input daily logs (temperature & humidity)
  3. Mark batch as "Export-Ready"

       ↓ Notification sent to exporter

[EXPORTER]
  4. View export-ready batches & check log data
  5. Acquire the batch
     → A "draft" certificate is automatically created
  6. Issue the certificate:
     a. Generate certificate PDF
     b. Compute SHA-256 of the document
     c. Send the hash to the Polygon Smart Contract
     d. Save tx_hash → status set to "published"
     e. Generate verification QR Code
  7. Release the batch to the marketplace (set a price)

       ↓ Batch appears on the marketplace

[BUYER]
  8. Find & purchase a batch on the marketplace
  9. Confirm payment
     → Digital ownership locked in the buyer's name
 10. Verify authenticity via QR / hash
     → System queries the Polygon Smart Contract
 11. Download the certificate PDF
```

---

## 🛠 Tech Stack

### Backend
| Technology | Version | Function |
|---|---|---|
| **PHP** | 8.3+ | Primary programming language |
| **Laravel** | v13 | Backend framework & API layer |
| **Laravel Sanctum** | v4 | Session & token-based authentication |
| **Laravel Queues** | — | Async blockchain processing |
| **DomPDF** | v3 | Certificate PDF generation |
| **MySQL** | — | Primary operational database |

### Frontend
| Technology | Version | Function |
|---|---|---|
| **Vue.js** | v3 | SPA (Single Page App) framework |
| **Vue Router** | v5 | Page navigation |
| **Pinia** | v3 | State management |
| **Chart.js** | v4 | Post-harvest log charts |
| **Leaflet** | v1.9 | Interactive farm location map |
| **Axios** | v1 | HTTP client |
| **TailwindCSS** | v4 | Styling |
| **Vite** | v8 | Frontend bundler |

### Blockchain
| Technology | Function |
|---|---|
| **Polygon Amoy Testnet** | Blockchain network for hash registration |
| **Solidity Smart Contract** | Stores & verifies certificate hashes |
| **ethers.js / Laravel-Web3** | Server-side blockchain interaction library |
| **Alchemy / Infura** | RPC Provider (gateway to the Polygon network) |

---

## 📁 Project Structure

```
biji-kopi/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # API controllers per module
│   │   ├── Middleware/        # Auth & role middleware
│   │   └── Requests/          # Form validation
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic (BlockchainService, etc.)
│   └── Traits/                # Reusable logic
├── context/                   # Design documents (SRS, SDD, UI Guide, DB Schema)
├── database/
│   ├── migrations/            # Database table schemas
│   ├── factories/             # Factories for testing
│   └── seeders/               # Initial data
├── resources/
│   ├── css/                   # Global stylesheets
│   └── js/
│       ├── views/             # Vue pages per role
│       │   ├── auth/          # Login, register, forgot password
│       │   ├── farmer/        # Farmer dashboard
│       │   ├── exporter/      # Exporter dashboard
│       │   └── buyer/         # Buyer dashboard
│       ├── components/        # Reusable Vue components
│       ├── stores/            # Pinia stores
│       └── router/            # Vue Router configuration
├── routes/
│   ├── api.php                # All API endpoints
│   └── web.php                # Catch-all for SPA
└── tests/                     # Feature & unit tests (PHPUnit)
```

---

## 🚀 Running Locally

### Prerequisites
- PHP 8.3+
- Composer
- Node.js & npm
- MySQL

### Installation Steps

**1. Clone the repository**
```bash
git clone https://github.com/veryepiccindeed/biji-clean.git
cd biji-kopi
```

**2. Automated setup (single command)**
```bash
composer run setup
```

This command will automatically:
- Install all PHP dependencies (`composer install`)
- Copy `.env.example` to `.env`
- Generate the application key
- Run database migrations
- Install Node.js dependencies
- Build frontend assets

**3. Configure the database**

Edit the `.env` file and adjust the database credentials:
```env
DB_DATABASE=biji_kopi
DB_USERNAME=root
DB_PASSWORD=
```

Then run migrations (if not already done):
```bash
php artisan migrate
```

**4. Start the development server**
```bash
composer run dev
```

This command runs **four processes simultaneously** in parallel:
- 🟦 `php artisan serve` — Laravel server at `http://localhost:8000`
- 🟣 `php artisan queue:listen` — worker for blockchain job queues
- 🟥 `php artisan pail` — live log viewer
- 🟠 `npm run dev` — Vite HMR for frontend hot reload

Open your browser to: **`http://localhost:8000`**

---

## 🔧 Environment Variables

Copy `.env.example` to `.env`, then adjust the following values:

```env
# Application
APP_NAME=BIJI
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biji_kopi
DB_USERNAME=root
DB_PASSWORD=

# Queue (required for blockchain jobs)
QUEUE_CONNECTION=database

# Blockchain (fill in with your Alchemy/Infura configuration)
# BLOCKCHAIN_RPC_URL=
# BLOCKCHAIN_PRIVATE_KEY=
# BLOCKCHAIN_CONTRACT_ADDRESS=

# Email (for the password reset feature)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
```

> **⚠️ Security:** Never commit the `.env` file to version control. The blockchain private key in particular **must never** be exposed.

---

## 📡 API Overview

All API endpoints are under the `/api/` prefix and **require authentication** (using Laravel Sanctum). There are no public endpoints.

### Auth Module
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/register` | Register a new account |
| `POST` | `/api/auth/login` | Login, obtain a session |
| `POST` | `/api/auth/logout` | Logout the active session |
| `POST` | `/api/auth/forgot-password` | Send a reset link via email |
| `POST` | `/api/auth/reset-password` | Reset password with a token |

### Farmer Module (Production)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/productions` | List of the farmer's own batches |
| `POST` | `/api/productions` | Create a new harvest batch |
| `GET` | `/api/productions/{id}` | Batch detail + its logs |
| `PATCH` | `/api/productions/{id}/status` | Update batch status |
| `POST` | `/api/productions/{id}/logs` | Add a daily log |

### Exporter Module (Acquisition & Certification)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/productions/available` | Farmer batches ready to be acquired |
| `POST` | `/api/productions/{id}/acquire` | Acquire a batch |
| `POST` | `/api/certificates/{id}/mint` | Issue to the blockchain |
| `POST` | `/api/certificates/{id}/retry-mint` | Retry if it failed |
| `PATCH` | `/api/certificates/{id}/release` | Release to the marketplace |

### Buyer Module (Marketplace & Verification)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/marketplace` | List of batches for sale |
| `POST` | `/api/orders` | Create a purchase order |
| `POST` | `/api/orders/{id}/confirm-payment` | Confirm payment |
| `GET` | `/api/verify/{hash}` | Verify authenticity via hash |
| `GET` | `/api/scan-histories` | Verification history |


---

## 🗄 Database

The system uses **6 main tables** that are interrelated:

| Table | Function |
|---|---|
| `users` | All system actors (farmers, exporters, buyers) with roles & personal preferences |
| `productions` | Harvest batch data (variety, GPS, elevation, status) |
| `production_logs` | Daily temperature & humidity logs per batch |
| `certificates` | Digital certificates (PDF hash, blockchain status, QR code, price) |
| `orders` | History of batch purchase transactions by buyers |
| `blockchain_logs` | Audit trail of every interaction with the Polygon network |
| `scan_histories` | History of certificate verifications by buyers |

**Table relationships (summary):**
```
users
 ├── productions (as farmer_id)
 │    └── production_logs
 │    └── certificates (1-to-1, UNIQUE constraint → prevents double acquisition)
 │         ├── orders
 │         ├── blockchain_logs
 │         └── scan_histories
 ├── certificates (as exporter_id)
 └── certificates (as buyer_id, filled in after purchase is complete)
```

> The `production_id` column in the `certificates` table has a `UNIQUE` constraint, which serves as the primary safeguard to ensure a single batch cannot be claimed by more than one exporter.


---

## ⛓ Blockchain & Smart Contract

### How It Works (Simply)

When an exporter issues a certificate, the system performs the following steps:
1. **Generate a PDF** of the formal certificate
2. **Compute a fingerprint** of the document using the SHA-256 algorithm (producing a unique 64-character code)
3. **Send the fingerprint** to the Smart Contract on the Polygon network
4. The blockchain **stores it permanently** — anyone can prove that the document was registered at a specific point in time

When a buyer wants to **verify**, the system calls the `verifyHash()` function on the Smart Contract. If the hash matches, the document is authentic. If it does not match, the document has likely been tampered with.

### Smart Contract (Summary)

```solidity
// Store a document hash on the blockchain (admin only)
function recordHash(bytes32 _hash) external onlyAdmin;

// Returns true if the hash has been registered
function verifyHash(bytes32 _hash) external view returns (bool);

// Retrieves the registration timestamp for audit purposes
function getTimestamp(bytes32 _hash) external view returns (uint256);
```

### Failure Handling

If submission to the blockchain fails (e.g., the network is busy), the system automatically retries up to **3 times** with increasing delays (30 seconds → 2 minutes → 10 minutes). The exporter can also trigger a manual retry from the dashboard if needed.

---

## 🎨 Design & UI

BIJI is designed around the concept of **"Earth & Technology"** — blending the warmth of agrarian aesthetics with the precision of a modern technology application.

### Design Principles
- **Dark mode** as default, with a very dark brown background (`#0F0D0B`), not pure black
- **Premium typography**: Playfair Display (headings), DM Sans (content), JetBrains Mono (code/hashes)
- **Different accent colors per role** automatically applied after login
- Cards with a **thin glowing green border effect** for a futuristic feel
- **Responsive**: Mobile-first for farmers, desktop-first for exporters

### Responsiveness
| Screen | Primary User |
|---|---|
| Mobile (< 768px) | Farmer (bottom navigation bar, thumb-friendly forms) |
| Tablet (768–1024px) | Exporter (hidden sidebar) |
| Desktop (> 1024px) | Exporter (full sidebar), Buyer |


---

## 🧪 Running Tests

This project uses **PHPUnit** for testing. All critical features must be covered by tests before being considered production-ready.

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/ProductionTest.php

# Filter by test name
php artisan test --compact --filter=testAcquireBatch
```

Important scenarios that must be tested:
- Batch ownership transfer (acquisition & purchase)
- Prevention of double acquisition by two exporters simultaneously (*concurrent requests*)
- Certificate minting flow and blockchain failure handling



## 🤝 Contributing
This project was developed as a final academic project (ALP Semester 6) by:
* [@veryepiccindeed](https://github.com/veryepiccindeed) - Backend & DevOps Architect
* [@bigbosspramana](https://github.com/bigbosspramana) - Frontend & DevOps Engineer
* [@Apryadi](https://github.com/Apryadi) - UI/UX & Smart Contract Developer
* [@FranklinJaya2006](https://github.com/FranklinJaya2006) - Database & IoT Engineer
* [@chaidenfoanto](https://github.com/chaidenfoanto) - ML Engineer
* [@levinn1](https://github.com/levinn1) - ML Engineer


 If you would like to contribute:

1. Fork this repository
2. Create a feature branch: `git checkout -b feature/feature-name`
3. Commit your changes: `git commit -m 'feat: add feature X'`
4. Push to the branch: `git push origin feature/feature-name`
5. Create a Pull Request

### Code Standards
- Follow **PSR-12** conventions for PHP
- Run `vendor/bin/pint --dirty` before committing to auto-format PHP
- Every new feature **must be accompanied by tests**
- Use `php artisan make:` to create new files (controllers, models, migrations, etc.)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

<p align="center">
  Made with ❤️ for more transparent and trustworthy Sulawesi coffee.
</p>
