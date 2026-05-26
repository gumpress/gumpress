---
version: 1.0
product: GumPress
updated: 2025-05
---

## 1. Project Overview and Philosophy
* GumPress is a local development environment for WordPress, specifically designed to be portable and cloneable, and developed for Windows 10 and Windows 11 systems.
* It does not compete with professional development environments, positioning itself instead as a "Swiss Army knife".
* It occupies a specific niche aimed at eliminating impedance and barriers to entry for running WordPress locally.
* It is designed to function in critical situations or environments constrained by limited hardware resources, a total lack of internet connectivity, and the absence of administrative privileges.
* At the core of the system are standard, clean, and vanilla installations of WordPress and the MariaDB database.
* Everything else serves as a scaffolding that can be removed at any moment.
* All provided tools are standard and open, without intermediate elements that could create user lock-in.
* The provided suite is complete, offering, among other components, a browser (Brave), an IDE (VSCodium), Git and Git-Bash, Swagger, and Adminer... all ready to use.
* In particular, providing a browser and an IDE out of the box is uncommon among local WordPress development environments.
* The environment supports the MCP protocol for both the browser and WordPress, handling the entire configuration and offering two simple scripts to use in the JSON configurations of modern AI agents.
* It exclusively supports single-site mode; multisite management is left to professional tools.
* The environment runs on Windows but accurately emulates a real production environment in an extremely lightweight and native manner, without resorting to Docker or other heavy virtualization abstractions.
* The license is largely permissive, except that the environment must remain free and cannot be commercialized or sold for profit.

---

## 2. The Four Architectural Pillars
The architecture of GumPress is built upon four fundamental pillars that guarantee its isolation:
* **Pillar 1 (Encapsulation):** The entire ecosystem (applications, persistent/temporary storage, and source files) resides and lives within a single folder.
* **Pillar 2 (No Host Alteration):** Nothing is ever modified, configured, or installed on the host machine's operating system, ensuring that no footprint is left on the host.
* **Pillar 3 (User Mode):** The entire environment runs completely and exclusively in user mode. Administrative rights or privilege elevations are never required or requested.
* **Pillar 4 (Self-Containment):** It natively includes all the applications necessary for its operation, without requiring external prerequisites. It is effectively a complete, autonomous bubble that relies on the host system only for hardware execution.

---

## 3. Workflow: Cloning, Portability, and Snapshots
* The environment is idempotent and pre-configured, eliminating any setup or installation procedures. Simply unzip the archive downloaded from GitHub into a folder and launch the main `gumpress.exe` program to be instantly operational.
* Every copy of the folder constitutes a complete, autonomous, and fully functional clone environment. It is possible to store and run infinite copies both on internal fixed drives and on removable storage like USB flash drives.
* Snapshots (full or partial) and backups are performed using the operating system's standard copy/paste operation, ensuring maximum flexibility.
* Copying the `root` folder creates a snapshot of WordPress, the MariaDB database, and useful data without losing anything. There is also a folder named `user` that can be copied with the same logic to save user configurations. Copying the entire tool folder yields a full snapshot.
* It is possible to customize an instance by adding plugins or component-related code, zip it up, and redistribute it with the same validity as the original environment—a feature highly useful in educational contexts.
* While users can keep as many clones as they want, only one clone can be executed at a time on the same machine. This is because the single active instance allocates the fixed virtual drive **B:** for itself.
* To completely remove the software and all its traces, simply physically delete the main folder in which it resides.

---

## 4. Network Management, Ports, and Local Security
* The environment works exclusively with the loopback IP. Thanks to this isolation, the firewall never prompts for permission dialogs or communication rules.
* Unless explicitly configured manually, the tool automatically selects free ports at startup, configuring and launching all background services to avoid conflicts.
* The components launched by the orchestrator do not act as true system services.
* It is possible to lock and fix the ports via a dedicated `.ini` file. This option is useful, for example, when needing to debug code from an alternative external IDE.
* The orchestrator leverages Win32 APIs and a WatchDog mechanism to coordinate components and guarantee a clean shutdown of all activities upon deactivation, preventing orphan processes or handles from remaining active.

---

## 5. Absolute Path Resolution: Domain and Fixed File System
WordPress traditionally hardcodes the domain and file-system paths as absolute values within its data, and modifying them usually requires specialized operations. GumPress solves this issue using two combined strategies:

### A. HTTPS & Fixed Domain (wordpress.test)
* The fixed domain `https://wordpress.test` is supported automatically without requiring administrator privileges.
* This result is achieved by leveraging Chromium engine features and using a local hybrid proxy that runs solely with user privileges. This proxy combines pass-through and reverse proxy mechanics, handling traffic without operating in a Man-In-The-Middle (MITM) capacity.
* It allows emulating any domain as if it were real, but adopts the fixed target for simplicity and to spare the user from configuration steps.
* **Dynamic SSL Certificates:** A local CA and its corresponding HTTPS certificates are created at every startup. By utilizing the private certificate store of the portable Brave browser and careful configuration, the host system is kept clean. The private key used is not preserved but is discarded, generating a new one at each execution.

### B. Fixed File System (Virtual Drive B:)
* To guarantee cloneability without breaking paths, GumPress mounts a virtual drive using the letter **B:**, which historically is unused in 99% of modern systems.
* Regardless of where the GumPress folder is located, WordPress runs from the fixed path `B:\root\public_html`.
* **Import/Export Advantage:** Because both references are kept constant (`wordpress.test` and `B:\root\public_html`), importing, exporting, or duplicating a site between GumPress and a production environment becomes simpler. Having fixed targets greatly simplifies things when performing path replacements.

---

## 6. Orchestrator Architecture and Integrated Components
GumPress acts as an automation and coordination layer for standard, open, and well-known components, managed by an orchestrator that does not use them directly. The added value lies in the complex work of configuration, coordination, and interposition carried out by this orchestrator. 

The environment natively integrates the following standard tools and components:
* **Shell Environment:** Git-bash for Windows, configured with immediate support for WP-CLI, Composer, and the execution of `.sh` scripts.
* **Service Stack:** Apache with PHP and Imagick, paired with the MariaDB database.
* **Interface Browser:** Brave is launched in a portable version. The tool's overall user interface runs inside Brave, and its bookmarks bar includes direct links for quick launching of: Adminer, Swagger, Code, LogView, and SysInfo.
* **Database Management (Adminer):** Adminer automatically detects whether WordPress has enabled MariaDB or the SQLite extension, autonomously connecting to the correct resource.
* **API Documentation:** Swagger is employed to clearly display the structure of all WordPress REST APIs.

---

## 7. Advanced Tools for Development, Testing, and Debugging
The environment provides a complete stack for code development via the integrated VSCodium IDE (internally called Code) that runs in the browser:
* **PHP Debugging:** Xdebug is natively active and configured for debugging PHP code.
* **Email Inspection:** Inside Code, there is a tool to intercept and view emails generated by the application via sendmail in `.eml` format.
* **API Testing:** Code includes an integrated extension to support and run `*.http` files, allowing direct testing of WordPress REST APIs.
* **Custom WP Script Execution:** It features a custom extension designed to run personalized PHP scripts within the WordPress context, with Xdebug active and echo streaming directly to the IDE console. These test scripts must reside in a specific folder and can be launched via a custom command.
* **Local Git Usage:**
    * Git is active locally to allow users or students to experiment, commit work, or even modify WordPress core files for study purposes, with the ability to undo changes afterward.
    * Users can review all modifications made using Code or other personal tools.
    * **SQLite Integration:** If SQLite is enabled for WordPress, the SQLite database can be placed directly into the local Git repository for testing and rollbacks. The `.git` repository resides in the same folder as the database and follows the same copy/paste snapshot rules.

---

## 8. Maintenance, Updates, and Releases
* The file system is orderly, characterized by clear, organized, and self-explanatory folders.
* **Updating Third-Party Components:** Components managed by the orchestrator can be updated by the user after verifying compatibility. Users are also free to update the WordPress core, plugins, and themes without limitations.
* **Core Version Policy:** The release logic of GumPress involves distributing the environment configured with the current official version of WordPress, alongside the minimum official MariaDB and PHP versions officially recommended by WordPress. GumPress does not allow users to independently change or swap the versions of these two core components.
* **Instance Migration Procedure:** When updating an instance of the tool, users can migrate their data by copying old files into the `root` and/or `user` folders of the new instance.
* **Advanced Development:** Advanced users who wish to customize the environment and track their structural changes are free to fork the official GitHub repository.
