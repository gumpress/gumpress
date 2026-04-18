# GumPress

GumPress is a **free Open-Core** WordPress offline stack for Windows.
No install. No Docker. No admin rights. No internet required.
Just unzip and start. Whenever you want: tinker, customize, rezip, and redistribute your own version, freely.

<br>
<p align="center">
	<img src="docs/images/he1.png" alt="GumPress Overview" width="700">
	<br>
	<!--<em>GumPress Overview</em>-->
</p>

## Learn More

- **[Vision & Goals](docs/vision_goals.md)** – _(drafting)_ Why we built GumPress and who it is designed for.
- **[Core Concepts](docs/core_concepts.md)** – _(drafting)_ Discover the free Open-Core model, clonability, portability, and how to customize your own stack.
- **[Stack & Security](docs/stack_security.md)** – _(drafting)_ Included components, privacy commitments, and software integrity.

🚧 The documentation is currently being finalized. Sections marked as _drafting_ are being populated.

## Get Started

1. [**Download the latest release**](https://github.com/gumpress/gumpress/releases/latest) and extract it to a folder of your choice.
2. Launch the orchestrator (`gumpress.exe`) to start the environment.
3. Say "Wow!" 🤯 You're now ready to tinker.
4. Check the [**Changelog**](docs/changelog.md) to see what's new in this release.

> [!TIP]
> ❤️ **Finding&nbsp;GumPress&nbsp;useful&nbsp;?**&nbsp;Consider&nbsp;[supporting&nbsp;this&nbsp;project](docs/support.md).

## Integrity

GumPress prioritizes transparency. You can verify the orchestrator (`gumpress.exe`) integrity by comparing its **SHA&#8209;256 Hash** with the one
calculated on your local file using PowerShell `Get-FileHash gumpress.exe -Algorithm SHA256`.

<table>
  <tr>
	 <td><b>File</b></td>
	 <td><code>gumpress.exe</code></td>
	 <td><b>Hash</b></td>
	 <td><code><!--HASH-->3dfd8cc0ea0f25165b8ba7437931fbffeb8c4ac4314123e1eaf2086d1e237bea<!--HASH--></code></td>
  </tr>
</table>

## Security

> [!IMPORTANT]
> Windows might display SmartScreen and/or CA Security warnings during the **first run**.
> This is normal for unsigned independent software and expected when installing a custom local CA.
> Your action _is required only once and does NOT require administrator rights_.

<details>
	<summary><b>💡 If this happens, click here to view the steps to proceed</b></summary>
	<br>

|| Action A | Action B |
| :---: | :---: | :---: |
| **1.&nbsp;SmartScreen** | <img src="docs/images/ss1.png" width="350"><br>*Click **More info*** | <img src="docs/images/ss2.png" width="350"><br>*Click **Run anyway*** |
| **2.&nbsp;CA&nbsp;Security** | <img src="docs/images/ca1.png" width="350"><br>*Click **OK*** | <img src="docs/images/ca2.png" width="350"><br>*Click **Yes*** |

</details>

## Privacy

Due to our free redistribution policy, we cannot track adoption without a minimal heartbeat. We are committed to transparency and respect for user privacy.

* **What we collect**: Only an anonymous ID (GUID) and the version number (VERS).
* **Privacy First**: We do NOT collect personal info, hardware identifiers, or any data from your machine, user profile, or local projects.
* **Full Control**: The anonymous ID is locally generated, is not linked to your identity, and you can reset it at any time.

📜 For a detailed technical breakdown about telemetry, see the full [**PRIVACY**](./PRIVACY) file.

## License

GumPress is a software bundle that combines a proprietary management engine (**Orchestrator**) with several independent open-source components (**Third-Party**).

### 1. Orchestrator

The orchestrator (`gumpress.exe`) is licensed under the **GumPress Software License 1.0**.
* **Usage**: Free to use, configure, and distribute for personal or commercial development.
* **Main Restrictions**: Standalone resale and reverse engineering are prohibited. It is not designed for production environments.
* **Full Terms**: See the [**LICENSE**](./LICENSE) file.

### 2. Third-Party

The third-party components are separate works governed by their own original open-source licenses. Orchestrator license does not modify or restrict your rights under those terms.
For details about versions, licenses, and applied changes, refer to the technical manifests:

<table>
	<tr>
		<td><b>WordPress</b></td>
		<td><a href="root/@manifest.json">root/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>Apache</b></td>
		<td><a href="core/apache/@manifest.json">core/apache/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>PHP</b></td>
		<td><a href="core/php/@manifest.json">core/php/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>MariaDB</b></td>
		<td><a href="core/mariadb/@manifest.json">core/mariadb/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>LlamaCpp</b></td>
		<td><a href="core/llama-cpp/@manifest.json">core/llama-cpp/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>Ungoogled-Chromium</b></td>
		<td><a href="core/ungoogled-chromium/@manifest.json">core/ungoogled-chromium/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>VSCodium</b></td>
		<td><a href="core/vscodium/@manifest.json">core/vscodium/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>Git</b></td>
		<td><a href="core/git/@manifest.json">core/git/@manifest.json</a></td>
	</tr>
	<tr>
		<td><b>System</b></td>
		<td><a href="core/system/@manifest.json">core/system/@manifest.json</a></td>
	</tr>
</table>

## Project Structure

This is how the GumPress environment is organized after extraction:

```text
conf/                   ← Configuration files
  llama-cpp/            ← Models and related files
core/                   ← Components
docs/                   ← Documentation
root/                   ← Your main environment container
  wordpress/            ← Your local WordPress project
    .git/               ← Local repository metadata
    mailer_data/        ← Captured local emails
    public_html/        ← A clean WordPress installation
    stored_data/        ← Persistent data for WordPress
    tryout_code/        ← Scripts runnable in WordPress context via VSCodium
user/                   ← User data and temporary files           
gumpress.exe            ← The main launcher
gumpress.ini            ← The main configuration file
```

---

*Built with passion for the WordPress community.*