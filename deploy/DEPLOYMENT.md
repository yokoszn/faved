# Self-hosted Faved deployment

This runbook deploys this fork of the classic, single-user Faved. It does not contain Faved Cloud's Types, Fields, Extract, or Team features. OpenID Connect subjects on the allowlist share the one Faved library.

## Preconditions

1. Inspect source CT 1000 on the designated Proxmox host: OS must be Debian 12 or 13, rootfs/storage must support a full clone, and it must be suitable for an unprivileged clone with `nesting` and `keyctl`. Check its SSH host keys, machine ID, installed services, and existing secrets before cloning; do not replicate live credentials into the new CT. Record the PVE node, storage, bridge/VLAN, unused CT ID, and target IP in private inventory. The source CT is not modified.
2. Create a PVE API token scoped to the source CT, new CT ID or pool, and target datastore. Start with `VM.Audit`, `VM.Clone`, `VM.Allocate`, `VM.PowerMgmt`, `VM.Config.CPU`, `VM.Config.Memory`, `VM.Config.Disk`, `VM.Config.Network`, `VM.Config.Options`, `Datastore.Audit`, and `Datastore.AllocateSpace` at the narrowest applicable paths; PVE and provider versions may demand a narrower or wider exact set. Verify the actual provider operations with `tofu plan` and a reviewed apply; do not grant broad `PVEAdmin` by default. Store the API token outside Git and set `PROXMOX_VE_API_TOKEN` in the runner environment.
3. Create an OIDC confidential client with authorization code flow and PKCE S256. Register one exact callback URL: `https://YOUR_HOST/api/auth/oidc`. Obtain stable `sub` values from the IdP for authorized owners. The first allowed subject will create the single Faved account; further allowed subjects share it. Require MFA and manage group membership at the IdP.
4. Use the Faved fork and a reviewed 40-character commit SHA. The upstream and GitHub fork are public, so keep all host addresses, keys, and secrets out of the repository. Store the OIDC client secret in Ansible Vault or a protected secret manager; never commit it or put it in OpenTofu state.

## Provision

Copy `tofu/terraform.tfvars.example` to `tofu/terraform.tfvars`, replace all placeholders, then run `tofu init`, `tofu fmt -check`, `tofu validate`, and `tofu plan -out=faved.plan`. Review the full clone, network, and protection settings, then `tofu apply faved.plan`. The resource has `prevent_destroy` and Proxmox protection enabled.

After cloning, rotate `/etc/machine-id` and SSH host keys in the new CT before exposing SSH. Remove any cloned credentials or unrelated services. Check that CT 1000 had no mounted production data and that the new CT has its own rootfs. Add only the new CT IP to the Ansible inventory. Create a dedicated SSH host-key record after verification.

Copy `ansible/vars.example.yml` to a Vault-encrypted file and fill every value, including administrator keys. Run `ansible-galaxy collection install -r ansible/requirements.yml`, then `ansible-playbook -i ansible/inventory.ini -e @ansible/vars.vault.yml ansible/site.yml`. The playbook pins the application source to one commit, builds the image locally, binds HTTP to loopback, stores data at `/var/lib/faved/storage`, and mounts the OIDC secret from a restricted file rather than exposing it in `docker inspect`. After the first run, change the Ansible inventory to `ansible_user=faved-admin`; root SSH is disabled.

The first visit creates the SQLite database. Then use the SSO button. Keep the hostname private until login and logout, OIDC callback, denied subject, and API authorization checks pass. Configure cloudflared manually in this CT with its origin set to `http://127.0.0.1:8080`; the browser-facing hostname must use HTTPS. If cloudflared runs elsewhere, change the bind address deliberately and enforce a source-restricted firewall rule. Do not open port 8080 publicly.

## Access and operations

| Role | Access |
| --- | --- |
| PVE infrastructure admin | Scoped API token for cloning/maintaining the Faved CT; separate human break-glass PVE admin. |
| CT system admin | SSH key to `faved-admin`, unrestricted sudo inside this CT, no PVE host access. |
| Faved operator | SSH key to `faved-operator`; sudo permits only `systemctl restart faved.service`, no Docker socket or sudo shell. Unprivileged `systemctl status` remains available. |
| Faved end user | OIDC subject allowlist, then the application's single-user session; no CT or PVE access. |

`root` SSH and password login are disabled after the administrator key is installed. Do not add operators to the Docker group: Docker socket access is effectively root access inside the CT. Cloudflared credentials stay outside the app volume and are managed by the person configuring the tunnel.

SSO sessions last at most eight hours. The server checks the subject allowlist on every authenticated request, so removing a subject from the configuration revokes its existing Faved session once the service restarts. Removing a user only at the IdP does not invalidate an existing Faved session immediately; rotate the allowlist or wait for the eight-hour expiry. App logout clears the Faved session, not the IdP session.

## Backups and recovery

Back up `/var/lib/faved/storage` (SQLite DB and images) and `/etc/faved/oidc.env` separately with encryption and restricted restore access. Use a stopped service or SQLite online backup for a consistent DB snapshot; test restoring into an isolated CT at least once. Include the CT configuration, reviewed Git commit, and IdP client registration in recovery notes. A PVE snapshot alone is not proof of recoverability.

For updates, review a new upstream commit and dependency audit, update the pinned SHA, run Ansible, verify `docker compose ps`, `/api/auth/oidc/config`, login, bookmark read/write, and logout, then capture a backup. Roll back by returning to the prior Git SHA and restoring DB only if a schema migration requires it.

## Security findings and gates

| Finding | Source evidence | Treatment |
| --- | --- | --- |
| Default no-user mode lets anyone with network access use the app and claim initial ownership. | `AuthenticationMiddleware.php`, `UserCreateController.php` | OIDC mode fails closed for data APIs before a user exists; publish only after an owner subject is allowlisted. |
| Password login and account deletion could bypass or disable SSO. | `AuthLoginController.php`, `UserDeleteController.php` | Both are denied in OIDC mode; password changes and local account creation are also denied. |
| Login did not rotate the session identifier. | `framework/helper-functions.php` | Rotate on login and logout. |
| Composer lock had 13 advisories in Guzzle packages at audit time. | `composer.lock`, `composer audit` | Updated Guzzle packages; rerun audit at each build. |
| Frontend lockfile had nine advisories at audit time, including one critical build dependency issue. | `frontend/package-lock.json`, `npm audit` | Updated the lockfile and React Router; rerun build and audit at each release. |
| Upstream Docker Compose binds ports on all interfaces and uses an unpinned image tag. | `docker-compose.yml` | This deployment binds loopback only and builds a reviewed commit. |
| An internet-facing container can request arbitrary page metadata. | `init.php`, `utils/safe-http-functions.php` | SSRF guard is present; keep egress controls, DNS, redirects, and private-address rejection under review. |
| Bookmark HTML import only rejected lowercase `javascript:` and accepted other unsafe schemes. | `utils/BookmarkImporter.php` | Import now accepts only valid HTTP(S) URLs, case insensitively. Review any bookmarks imported before this change. |
| Apache enabled CGI, Includes, and unrestricted `.htaccess` overrides under the public directory. | `apache-conf/faved.conf` | Disabled those features and directory listing in the fork. |

Remaining gates: a real IdP callback test, Proxmox source/template inspection, CT clone validation, PVE firewall check, restore exercise, and cloudflared origin test. None is established by a local build alone.
