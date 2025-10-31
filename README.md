# 🛍️ Slkstore: The Software Center for Slackware

* This is pre-release - not functional

Pre-Release 1.3 - Careful, install ready

* All the update and remove functions are not included in the code until they are 100% tested.

```
slkstore - Package Store for Slackware 64-bit Stable

Description:
  A package management store specifically designed for Slackware Linux
  64-bit stable releases. Provides an easy-to-use interface for browsing,
  searching, and managing Slackware packages.

Usage: slkstore [options]

Options:
  --help          Show this help message
  --local         Run PHP server on 127.0.0.1:8000 (localhost only, no GUI)
  --remote        Run PHP server on 0.0.0.0:8000 (accessible from network, no GUI)
  (no options)    Run with GUI and local server on 127.0.0.1:8000

Configuration:
  The program reads settings from 'systemc' file in the current directory.
  Configuration options: width, height, title, showMaximize, showMinimize

Examples:
  slkstore          # Start with GUI (default mode)
  slkstore --local  # Start local server without GUI
  slkstore --remote # Start remote server without GUI

For more information, visit: https://www.slackware.com

```
---

<img src="https://files.mastodon.social/media_attachments/files/115/458/159/261/362/824/original/f7ef5198a44e6fd8.png" alt="Slkstore screenshot" width="400">

---

## 🚀 What is Slkstore?

Slkstore is a web-based software center designed specifically for **Slackware Linux**. It provides a user-friendly graphical interface to browse, search, and manage software packages available from the [SlackDCE](https://slackware.uk/slackdce/) repository.

The main goal of Slkstore is to simplify software management on Slackware, offering an experience similar to the "app stores" found in other operating systems, but with the simplicity and power that characterizes Slackware.

## ✨ Main Features

*   **🗂️ Software Catalog:** Browse a wide collection of software available for Slackware.
*   **🔍 Integrated Search:** Quickly find the applications you need with a powerful and fast search.
*   **📄 Detailed View:** Get all the information about each package, including its description, version, and dependencies.
*   **🎨 Graphical Interface:** An intuitive and clean visual design with icons for each application, making software identification easy and enjoyable.

---

## 🛠️ Technologies Used

*   **[Qt5](https://www.qt.io/)**: For the graphical interface.
*   **[PHP](https://www.php.net/)**: As the backend language.
*   **[Slackware Linux](http://www.slackware.com/)**: The target operating system.

---

## 📄 License

This project is under the **Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)** license.

---

## 👨‍💻 Author

**Eduardo Castillo**
*   📧 **Email:** [hellocodelinux@gmail.com](mailto:hellocodelinux@gmail.com)
*   🌐 **Repository:** [SlackDCE](https://slackware.uk/slackdce/)
*   📦 **Slackdce manifest:** [MANIFEST.txt](https://slackware.uk/slackdce/MANIFEST.txt)

---
*Created with ❤️ for the Slackware community.*

v1.3