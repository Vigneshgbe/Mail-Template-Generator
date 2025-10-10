---

# 💌 Email & Outlook Mail Template Generator

A fully customizable **Mail Template Builder** that allows users to **design, generate, and download branded email templates** for Outlook or any email client — complete with banners, messages, images, and footers.

Built using **Core PHP, JavaScript, HTML5, and CSS3**, this project combines **form-based inputs**, **rich text editing**, **carousel image integration**, and **PDF export automation**, enabling marketing and HR teams to create professional mailers within minutes — **no coding required**.

---

## 🚀 Project Overview

This tool empowers teams to **generate consistent and visually appealing email templates** for newsletters, internal communications, or marketing campaigns.
Users can upload banners, write messages with a rich-text editor, add carousels and footer images, and instantly generate downloadable HTML or PDF versions.

It also provides a **“Copy Template”** option (Ctrl + C) for quick use in Outlook, Gmail, or web-based editors.

---

## 🧩 Core Modules

### 🧾 1. User Details Form

* Basic info collection (Sender name, Subject, Company, etc.)
* Validation-enabled form with real-time preview.

### 🖼️ 2. Banner Upload

* Upload top banner image (hero section).
* Supports image compression and preview.

### 📝 3. Message Composer

* In-built **WYSIWYG text editor (TinyMCE / CKEditor)** for rich formatting.
* Bold, italics, hyperlinks, bullet points, and alignment options.
* Live preview of message content inside mail layout.

### 🖼️ 4. Carousel / Multi Image Upload

* Upload 2–5 images to form a horizontal scrolling carousel.
* Auto-generate image slider using JavaScript.
* Dynamic add/remove image options with thumbnail preview.

### 🧩 5. Footer Image Upload

* Optional footer logo or branding image.
* Auto-aligns with company theme and background color.

### 📤 6. Template Export & Share

* **Generate Mail Template (HTML)** → One-click download.
* **Download PDF version** → Perfect for preview or approval sharing.
* **Copy Template to Clipboard (Ctrl + C)** → Ready to paste in Outlook or Gmail.

---

## ⚙️ Key Features

✅ Fully Responsive UI with Modern Email Layout
✅ Upload Banner, Carousel, and Footer Images
✅ Rich Text Editor (WYSIWYG) for Message Writing
✅ Real-time Preview and Auto Alignment
✅ PDF Download & HTML Template Export
✅ Copy-to-Clipboard Support (for Outlook)
✅ Drag & Drop Image Upload with Validation
✅ Optimized for Gmail and Outlook Rendering

---

## 🛠️ Tech Stack

| Category          | Technologies Used               |
| ----------------- | ------------------------------- |
| **Frontend**      | HTML5, CSS3, JavaScript         |
| **Backend**       | PHP (Core PHP)                  |
| **Text Editor**   | TinyMCE                         |
| **PDF Export**    | jsPDF / DOMPDF                  |
| **Carousel**      | JavaScript / Bootstrap Carousel |
| **File Handling** | PHP File Upload + Validation    |

---

## 🗂️ Folder Structure

```
Mail-Template-Generator/
│
├── assets/                # CSS, JS, and icon files
├── uploads/               # User-uploaded images (banners, carousel, footer)
├── templates/             # Generated HTML templates
├── index.php              # Main interface & form
├── index-new.php          # Template generation logic
├── export.php             # PDF download handler
├── 1.php                  # Clipboard integration
├── 2.php                  # Alternative template design
├── style.css              # Custom styling
├── README.md              # Project documentation
└── config.php             # Path and upload settings
```

---

## 🧠 Workflow

1. **Fill Form** → Add sender details & subject.
2. **Upload Banner** → Image preview appears immediately.
3. **Write Message** → Use text editor to design email body.
4. **Add Carousel / Multi Images** → Select up to 5 images with preview.
5. **Add Footer Image (Optional)** → Logo or signature image.
6. **Click “Generate Template”** → View real-time HTML preview.
7. **Export as PDF** or **Copy Template (Ctrl + C)** to paste in Outlook.

---

## 🏆 Highlights

* ⚡ Designed a complete **end-to-end email builder** with UI + backend logic.
* 💬 Supports **Outlook, Gmail, and other clients** with optimized formatting.
* 📄 Auto-generates **PDF versions** for official previews.
* 🔄 Interactive **carousel generation** and **multi-image selection**.
* 🧰 Used **pure PHP & JS** (no frameworks) for full control and simplicity.

---

## 💡 Future Enhancements

* Cloud save option for reusable templates.
* Predefined company-branded templates.
* Dark/light mode preview toggle.
* Inline image embedding for Outlook-safe export.
* QR code integration for quick template sharing.

---

## 🧾 License

Open for learning and portfolio demonstration purposes.
Commercial reuse requires permission from the author.

---

**Developed with ❤️ by [Vignesh G](https://github.com/Vigneshgbe)**

> *Building tools that make creativity and communication faster.*

---
