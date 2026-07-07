# QA Server Deployment Guide

This guide details the automatic deployment configuration for the **Unity App** QA server using GitHub Actions.

---

## 1. Workflow Metadata

* **Workflow File**: `.github/workflows/deploy-qa.yml`
* **Trigger Branch**: `develop` (Also supports manual triggers via `workflow_dispatch`)
* **Target Path**: `/home/devunity/laravel`

---

## 2. Deployment Sequence

When a deployment is triggered, the following commands are run sequentially on the QA server via SSH:

```bash
cd $QA_DEPLOY_PATH
git fetch origin
git checkout develop
git reset --hard origin/develop
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan reverb:restart
```

---

## 3. Required GitHub Secrets

Configure the following secrets in the GitHub Repository settings under **Settings** -> **Secrets and variables** -> **Actions**:

| Secret Name | Description | Example / Note |
| :--- | :--- | :--- |
| `QA_SSH_HOST` | IP address or domain name of the QA server | `192.168.1.100` or `qa.peersunity.com` |
| `QA_SSH_USER` | SSH username to log in as | `devunity` |
| `QA_SSH_PORT` | SSH port (defaults to `22` if not specified) | `22` (or a custom port) |
| `QA_SSH_KEY` | Private SSH key authorized on the QA server | Raw contents of the private key |
| `QA_DEPLOY_PATH` | Directory where the application resides on the QA server | `/home/devunity/laravel` |

---

## 4. How to Configure GitHub Secrets

1. Navigate to your GitHub repository: `DevelopersAequitas/unity-app-new`.
2. Go to **Settings** (top navigation bar) -> **Secrets and variables** (left sidebar) -> **Actions**.
3. Click on **New repository secret**.
4. Input the name (e.g., `QA_SSH_HOST`) and the corresponding value.
5. Click **Add secret**. Repeat for all five required variables.

---

## 5. How to Rotate SSH Keys

To maintain security, follow these steps to rotate the SSH deployment key pair:

1. **Generate a new key pair** locally or on a secure terminal:
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-deploy-qa" -f ./id_ed25519_qa
   ```
   *Do not set a passphrase for this deployment key.*

2. **Authorize the new public key** on the QA server:
   - Copy the content of `./id_ed25519_qa.pub`.
   - Log into the QA server.
   - Append the content of the public key to `/home/devunity/.ssh/authorized_keys`:
     ```bash
     echo "YOUR_NEW_PUBLIC_KEY_CONTENT" >> ~/.ssh/authorized_keys
     ```

3. **Update the GitHub Secret**:
   - Copy the content of the private key `./id_ed25519_qa`.
   - Update the `QA_SSH_KEY` value in GitHub Repository Secrets.

4. **Revoke the old key**:
   - Edit `/home/devunity/.ssh/authorized_keys` on the QA server and delete the old key entry.

---

## 6. How to Manually Trigger Deployment

There are two ways to deploy manually:

### Option A: From the GitHub Actions Interface
1. Go to the **Actions** tab of your repository on GitHub.
2. Select **Deploy to QA Server** from the left sidebar.
3. Click the **Run workflow** dropdown on the right side.
4. Select the `develop` branch and click **Run workflow**.

### Option B: Directly on the QA Server (Terminal)
If you need to deploy code manually without GitHub Actions, log in to the QA server and run:
```bash
cd /home/devunity/laravel
git fetch origin
git checkout develop
git reset --hard origin/develop
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan reverb:restart
```

---

## 7. How to Disable Deployment

* **To pause temporarily**: Go to **Settings** -> **Actions** -> **General** on GitHub. Under *Actions permissions*, you can select *Disable actions* to pause all pipelines.
* **To disable this specific workflow**: Go to the **Actions** tab, select **Deploy to QA Server**, click the three dots (`...`) in the top right of the workflow run history list, and select **Disable workflow**.
* **To delete permanently**: Remove the `.github/workflows/deploy-qa.yml` file from the `develop` branch.

---

## 8. Troubleshooting Failures

If a deployment fails, open the specific run in the GitHub Actions dashboard to pinpoint the failing step:

* **SSH Connection Fails**:
  - Verify that the QA server firewall allows incoming connections on the SSH port from GitHub IP ranges.
  - Check that `QA_SSH_HOST`, `QA_SSH_USER`, and `QA_SSH_PORT` are configured correctly.
  - Verify that the private key in `QA_SSH_KEY` matches the public key authorized in `authorized_keys` on the server.
* **Git Pull/Fetch Fails**:
  - Verify that the user `devunity` has correct git access. Run `ssh -T git@github.com` on the QA server to verify SSH authorization with GitHub.
* **Composer Install Fails**:
  - Ensure the PHP CLI version matches Laravel 12 requirements (`^8.2`).
  - Verify the server has access to the internet to download packages.
* **Artisan Commands Fail**:
  - Check server-side logs (`storage/logs/laravel.log`) for DB connection errors or configuration issues.
  - Ensure Postgres database services (or configured DB connection) are running.

---

## 9. Security Considerations

1. **Least Privilege**: Ensure the SSH user (`devunity`) has access only to directories they need to manage (such as `/home/devunity/laravel` and write permissions to `storage` and `bootstrap/cache`). Never use the `root` user for deployment.
2. **Secrets Encryption**: GitHub encrypts secrets at rest. They are masked in GitHub Actions log outputs to prevent exposure.
3. **No Passphrase**: The private key must not have a passphrase, as GitHub Actions runs in a non-interactive shell.
4. **Key Authorization Restriction**: You can restrict the key usage in the `/home/devunity/.ssh/authorized_keys` file by prefixing it with IP restrictions if needed.

---

## 10. Rollback Strategy

Since the workflow uses hard-resetting to a specific branch point (`git reset --hard origin/develop`), rolling back is fast and simple:

1. **Revert on Git**:
   - Revert the problematic commit on the `develop` branch locally:
     ```bash
     git revert HEAD
     git push origin develop
     ```
   - This push will automatically trigger the deployment pipeline, deploying the reverted stable state to the QA server.

2. **Manual Rollback (Fastest)**:
   - If the pipeline is failing, SSH directly to the QA server and force reset to a known stable commit hash:
     ```bash
     cd /home/devunity/laravel
     git reset --hard <STABLE_COMMIT_HASH>
     php artisan optimize:clear
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     php artisan queue:restart
     php artisan reverb:restart
     ```
