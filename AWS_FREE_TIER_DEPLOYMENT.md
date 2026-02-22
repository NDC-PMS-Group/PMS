# Deploy NDC-PMS on AWS Free Tier (EC2 + Docker)

This guide deploys your existing `docker-compose.yml` on one EC2 instance.

## 0. Free Tier notes
- Free Tier rules changed based on account creation date. Check current EC2 Free Tier policy here:
  - https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-free-tier-usage.html
- EC2 launch guide:
  - https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/LaunchingAndUsingInstances.html

## 1. Create EC2 instance
- Region: choose one near your users.
- AMI: `Ubuntu Server 22.04 LTS`.
- Instance type: `t2.micro` or `t3.micro` (depending on region/account free-tier eligibility).
- Storage: keep within Free Tier allowance.
- Key pair: create/download `.pem`.

### Security Group (inbound)
Create/attach a security group with:
- SSH `22` from **your IP only** (`x.x.x.x/32`)
- HTTP `80` from `0.0.0.0/0`
- HTTPS `443` from `0.0.0.0/0` (for later SSL)

AWS security group reference:
- https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/creating-security-group.html

## 2. Connect to EC2

```bash
chmod 400 your-key.pem
ssh -i your-key.pem ubuntu@<EC2_PUBLIC_IP>
```

## 3. Install Docker + Compose plugin
Follow Docker official Ubuntu docs:
- https://docs.docker.com/engine/install/ubuntu/

Quick commands:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER
newgrp docker
```

## 4. Pull your project to EC2

```bash
sudo apt-get install -y git
git clone <your-repo-url>
cd ndc-pms
```

## 5. Configure backend env (recommended)

```bash
cp pms-backend/.env.docker.example pms-backend/.env.docker
```

Edit if needed:

```bash
nano pms-backend/.env.docker
```

Important for production:
- set a fixed `APP_KEY` (do not leave blank forever)
- set `APP_DEBUG=false`
- set `APP_URL` to your real domain/IP

## 6. Start containers

```bash
docker compose up -d --build
```

Check:

```bash
docker compose ps
docker compose logs -f --tail=100
```

App URL:
- `http://<EC2_PUBLIC_IP>`

## 7. (Optional) Point domain
At your DNS provider:
- Add `A` record: `yourdomain.com` -> `<EC2_PUBLIC_IP>`

## 8. (Optional but recommended) HTTPS
Fast path for beginners:
- Put Cloudflare in front (SSL Full/Strict), or
- Add a reverse-proxy with automated Let's Encrypt (Caddy/Nginx Certbot).

## 9. Updates / redeploy

```bash
cd ndc-pms
git pull
docker compose up -d --build
```

If server has local edits and `git pull` fails:

```bash
# safest: keep local edits first
git stash push -u -m "temp-server-edits"
git pull
docker compose up -d --build
```

## 10. Recommended workflow (best practice)
- Develop on your local machine using dev mode:
  - `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build`
- Commit and push to your branch (`alvindale`, etc.).
- Merge to deployment branch (`main` or your chosen release branch).
- On EC2:
  - `BRANCH=<deploy-branch> bash scripts/deploy-ec2.sh`

Manual deploy script options:

```bash
# default deploy (main branch, migrate=yes, seed=no)
bash scripts/deploy-ec2.sh

# deploy specific branch
BRANCH=alvindale bash scripts/deploy-ec2.sh

# deploy and run seeders
BRANCH=main RUN_SEEDERS=true bash scripts/deploy-ec2.sh
```

The script handles:
- stashing uncommitted VM changes (to avoid pull failure),
- pulling latest branch with fast-forward only,
- rebuilding/restarting containers,
- running migrations (and optional seeders),
- printing `docker compose ps` after deploy.

## 11. Optional CI/CD (GitHub Actions auto-deploy)
Workflow file:
- `.github/workflows/deploy-ec2.yml`

It deploys on push to:
- `main`
- `alvindale`

Required repository secrets:
- `EC2_HOST` (example: `174.129.149.39`)
- `EC2_USER` (example: `ec2-user`)
- `EC2_APP_DIR` (example: `/home/ec2-user/PMS`)
- `EC2_SSH_KEY` (contents of your private key, including BEGIN/END lines)

You can also run it manually via Actions tab (`workflow_dispatch`) and choose branch + optional seeding.

## 12. Backups (minimum)
- MySQL data is in Docker volume `db_data`.
- Periodically dump DB:

```bash
docker compose exec db mysqldump -undc -pndc_password ndc_pms > backup_$(date +%F).sql
```

## 13. Cost safety checklist
- Stop/delete non-free resources (EIPs not attached, extra volumes, load balancers).
- Keep one micro EC2 instance only.
- Review billing dashboard regularly.
