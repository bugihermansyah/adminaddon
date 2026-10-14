<div align="center">

<h1>☕ bugihermansyah</h1>

<p><strong>Ngoding, ngopi, repeat.</strong></p>

<p>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://filamentphp.com/"><img src="https://img.shields.io/badge/Filament-5-FDAE4B?style=for-the-badge&logo=laravel&logoColor=white" alt="Filament 5"></a>
</p>

<p><em>Membangun hal berguna, satu cangkir dan satu commit setiap kali.</em></p>

</div>

---

<div align="center">
<pre>
    ( (          ┌──┐
     ) )         │  │
  .-~~~~-.       ├──┤
 (  KOPI  )      │VAPE│
  \______/       │    │
    `--'         └─┬──┘
                   │
</pre>
<strong>MODE KOPI</strong>　·　<strong>JEDA VAPE</strong>
</div>

## Perkakas yang saya pakai

| Area | Teknologi |
| --- | --- |
| Backend | PHP 8.4 · Laravel 13 |
| Admin panels | Filament 5 |
| Data | MySQL · Eloquent |
| Workflow | Git · Linux · FlyEnv |

## Tentang project ini

Panel admin untuk melihat data penghuni, transaksi tamu, dan gambar capture dari sistem MySQL yang sudah ada.

## Manual install

Panduan ini memasang aplikasi dengan Nginx dan PHP-FPM. Aplikasi membutuhkan PHP 8.3 atau lebih baru; PHP 8.4 direkomendasikan. Lihat [persyaratan server Laravel 13](https://laravel.com/docs/13.x/deployment#server-requirements) dan [database yang didukung Laravel 13](https://laravel.com/docs/13.x/database#introduction).

### CentOS 7

Langkah berikut memasang PHP 8.3 dari repository Remi, Nginx, Composer 2, Supervisor, dan ekstensi yang digunakan aplikasi. Repository PHP 8.3 EL7 menyediakan paket `8.3.8`; lihat [daftar paket Remi untuk EL7](https://rpms.remirepo.net/enterprise/7/php83/x86_64/repoview/) dan [petunjuk Remi untuk PHP 8.3](https://blog.remirepo.net/post/2023/08/31/PHP-on-the-road-to-the-8.3.0-release).

Jalankan perintah berikut sebagai akun yang memiliki `sudo`.

#### Siapkan repository dan paket

Atur repository CentOS ke snapshot 7.9.2009 di [CentOS Vault](https://vault.centos.org/7.9.2009/):

```bash
sudo cp -a /etc/yum.repos.d/CentOS-Base.repo /etc/yum.repos.d/CentOS-Base.repo.bak
sudo tee /etc/yum.repos.d/CentOS-Base.repo >/dev/null <<'REPO'
[base]
name=CentOS-7.9.2009 - Base
baseurl=https://vault.centos.org/7.9.2009/os/$basearch/
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7

[updates]
name=CentOS-7.9.2009 - Updates
baseurl=https://vault.centos.org/7.9.2009/updates/$basearch/
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7

[extras]
name=CentOS-7.9.2009 - Extras
baseurl=https://vault.centos.org/7.9.2009/extras/$basearch/
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7
REPO

sudo yum clean all
sudo yum makecache
```

Pasang EPEL, Remi, lalu aktifkan repository PHP 8.3:

```bash
sudo yum install -y yum-utils \
  https://dl.fedoraproject.org/pub/archive/epel/7/x86_64/Packages/e/epel-release-7-14.noarch.rpm
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
sudo yum-config-manager --enable remi-php83
```

Pasang PHP-FPM, ekstensi PHP, Nginx, Git, dan Supervisor:

```bash
sudo yum install -y nginx git unzip curl ca-certificates supervisor firewalld policycoreutils-python \
  php php-cli php-fpm php-common php-mysqlnd php-curl php-mbstring php-xml \
  php-zip php-bcmath php-intl php-opcache

php -v
php -m | grep -E 'curl|fileinfo|mbstring|mysqli|mysqlnd|PDO|pdo_mysql|xml|zip'
```

Pasang Composer 2 dari installer resmi:

```bash
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r 'echo hash_file("sha384", "composer-setup.php");')"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
  echo 'Composer installer checksum mismatch' >&2
  rm -f composer-setup.php
  exit 1
fi

sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

#### Siapkan aset frontend

Bangun aset di komputer build dengan Node.js 20.19+ atau 22.12+ (contohnya Ubuntu 24.04), karena Vite 8 memerlukan versi tersebut. Dari checkout project yang sama:

```bash
npm install
npm run build
tar -czf adminaddon-build.tar.gz public/build
scp adminaddon-build.tar.gz USER_SERVER@HOST_SERVER:/tmp/
```

Setelah menyalin source project ke server seperti di langkah **Pasang aplikasi**, ekstrak hasil build di server:

```bash
tar -xzf /tmp/adminaddon-build.tar.gz -C /var/www/admin_addon_tamu
```

#### Pasang aplikasi

Buat database khusus aplikasi mengikuti SQL pada bagian [Siapkan database](#siapkan-database). Atur `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`; konfigurasi ini dipakai bersama untuk database aplikasi serta schema legacy `mst_vip`, `trs_vip`, dan `tcm_vip`.

Clone project dan siapkan environment:

```bash
sudo mkdir -p /var/www
sudo chown "$USER":nginx /var/www
cd /var/www
git clone https://github.com/bugihermansyah/adminaddon.git admin_addon_tamu
cd /var/www/admin_addon_tamu
cp .env.example .env
```

Isi `.env` sebelum migrasi. Gunakan schema database aplikasi tersendiri; SQL grant dan database dapat mengikuti bagian [Siapkan database](#siapkan-database).

```dotenv
APP_NAME="Admin AddOn"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.domain-anda

DB_CONNECTION=mysql
DB_HOST=IP_SERVER_MYSQL
DB_PORT=3306
DB_DATABASE=admin_addon_tamu
DB_USERNAME=admin_addon
DB_PASSWORD=password-database-yang-kuat

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Atur `RESIDENT_CSV_URLS`, `RESIDENT_CSV_INTERVAL_MINUTES`, dan `RESIDENT_CSV_ATOMICITY` di `.env` bila ingin memberi nilai awal. Setelah migrasi pertama, pengaturan tersebut dapat dikelola dari menu profil **Pengaturan sinkronisasi CSV**.

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Buat akun admin produksi satu kali menggunakan Tinker:

```bash
php artisan tinker --execute 'App\Models\User::create([
    "name" => "Administrator",
    "username" => "admin",
    "email" => "admin@domain-anda",
    "password" => "GANTI_DENGAN_PASSWORD_UNIK",
]);'
```

Ubah kepemilikan project supaya akun deploy menjadi pemilik dan PHP-FPM dapat menulis ke direktori runtime:

```bash
sudo chown -R "$USER":nginx /var/www/admin_addon_tamu
sudo chmod -R ug+rwX /var/www/admin_addon_tamu/storage /var/www/admin_addon_tamu/bootstrap/cache
sudo chmod 640 /var/www/admin_addon_tamu/.env
```

Atur SELinux agar Nginx/PHP-FPM dapat membaca source, menulis cache/log, mengakses database dan mengambil CSV melalui HTTPS:

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/admin_addon_tamu(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/admin_addon_tamu/storage(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/admin_addon_tamu/bootstrap/cache(/.*)?'
sudo restorecon -Rv /var/www/admin_addon_tamu
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_can_network_connect 1
```

#### Konfigurasi Nginx dan PHP-FPM

Jalankan PHP-FPM dengan akun `nginx`:

```bash
sudo sed -i \
  -e 's/^user = apache/user = nginx/' \
  -e 's/^group = apache/group = nginx/' \
  /etc/php-fpm.d/www.conf
```

Buat `/etc/nginx/conf.d/admin-addon.conf`:

```nginx
server {
    listen 80;
    server_name admin.domain-anda;
    root /var/www/admin_addon_tamu/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan service dan buka HTTP pada firewall:

```bash
sudo nginx -t
sudo systemctl enable --now php-fpm nginx supervisord firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

Pasang TLS untuk domain lalu ubah `APP_URL` menjadi `https://admin.domain-anda`.

#### Jalankan scheduler dan queue worker

Tambahkan cron sebagai user `nginx`:

```bash
sudo crontab -u nginx -e
```

Tambahkan baris:

```cron
* * * * * cd /var/www/admin_addon_tamu && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Buat `/etc/supervisord.d/admin-addon-worker.ini`:

```ini
[program:admin-addon-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/admin_addon_tamu
command=/usr/bin/php artisan queue:work database --queue=resident-sync --sleep=3 --tries=3 --timeout=180 --max-time=3600
user=nginx
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=300
redirect_stderr=true
stdout_logfile=/var/log/supervisor/admin-addon-worker.log
numprocs=1
```

Muat worker dan cek service:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
sudo systemctl status nginx php-fpm supervisord
```

Tombol **Sinkronkan sekarang** dan scheduler memakai worker ini. Perubahan URL, interval, atau atomicity melalui web tidak memerlukan restart worker.

### Ubuntu 24.04 LTS

Jalankan perintah berikut dari akun deploy yang memiliki akses `sudo`.

#### Pasang paket sistem

Ubuntu 24.04 menyediakan PHP 8.3. Perintah ini memasang Nginx, PHP-FPM, Composer, Supervisor dan ekstensi PHP yang diperlukan:

```bash
sudo apt update
sudo apt install -y nginx git unzip curl ca-certificates composer \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl php8.3-opcache supervisor
```

Vite 8 memerlukan Node.js 20.19+ atau 22.12+. Contoh ini memasang Node.js 24 dari NodeSource; [Ubuntu 24.04 tercantum sebagai platform yang didukung](https://github.com/nodesource/distributions#debian-and-ubuntu-based-distributions).

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x -o /tmp/nodesource_setup.sh
sudo -E bash /tmp/nodesource_setup.sh
sudo apt install -y nodejs

php -v
php -m | grep -E 'curl|fileinfo|mbstring|mysqli|mysqlnd|PDO|pdo_mysql|xml|zip'
composer --version
node --version
npm --version
```

Versi tersebut memenuhi [persyaratan Node.js Vite 8](https://vite.dev/blog/announcing-vite8.html).

#### Siapkan database

Buat database khusus aplikasi pada server MySQL yang dapat dijangkau oleh server web. Jangan gunakan schema `mst_vip` sebagai `DB_DATABASE`: migrasi Laravel membuat tabel admin, session, queue, cache, dan konfigurasi sinkronisasi pada database aplikasi.

Contoh SQL ini dijalankan oleh DBA pada MySQL. Ganti nama database, IP server aplikasi, dan password. Batasi akses jaringan MySQL hanya dari server aplikasi.

```sql
CREATE DATABASE admin_addon_tamu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'admin_addon'@'IP_SERVER_APLIKASI'
  IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_ACAK_YANG_KUAT';

GRANT ALL PRIVILEGES ON admin_addon_tamu.* TO 'admin_addon'@'IP_SERVER_APLIKASI';
GRANT SELECT, INSERT, UPDATE, DELETE ON mst_vip.penghuni TO 'admin_addon'@'IP_SERVER_APLIKASI';
GRANT SELECT ON mst_vip.pos TO 'admin_addon'@'IP_SERVER_APLIKASI';
GRANT SELECT ON mst_vip.config TO 'admin_addon'@'IP_SERVER_APLIKASI';
GRANT SELECT ON trs_vip.trs TO 'admin_addon'@'IP_SERVER_APLIKASI';
GRANT SELECT ON tcm_vip.* TO 'admin_addon'@'IP_SERVER_APLIKASI';
```

`DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` dipakai bersama untuk database aplikasi dan koneksi legacy `mst_vip`, `trs_vip`, serta `tcm_vip`. Semua schema tersebut harus dapat dijangkau dengan host dan akun yang sama.

#### Pasang aplikasi

```bash
cd /var/www
git clone https://github.com/bugihermansyah/adminaddon.git admin_addon_tamu
cd /var/www/admin_addon_tamu
cp .env.example .env
```

Edit `.env` sebelum menjalankan migrasi:

```dotenv
APP_NAME="Admin AddOn"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.domain-anda

DB_CONNECTION=mysql
DB_HOST=IP_SERVER_MYSQL
DB_PORT=3306
DB_DATABASE=admin_addon_tamu
DB_USERNAME=admin_addon
DB_PASSWORD=password-database-yang-kuat

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Atur juga `RESIDENT_CSV_URLS`, `RESIDENT_CSV_INTERVAL_MINUTES`, dan `RESIDENT_CSV_ATOMICITY` bila ingin mengisi nilai awal pada migrasi pertama. Sesudahnya URL dan jadwal dapat diubah melalui menu profil **Pengaturan sinkronisasi CSV**. URL yang dimasukkan harus berasal dari CSV untuk cluster yang sama.

Contoh nilai awal opsional di `.env`:

```dotenv
RESIDENT_CSV_URLS="https://sales.paramount-land.com/api/v1/files/estate/gate/PEI-P01494.csv,https://sales.paramount-land.com/api/v1/files/estate/gate/PI-28980.csv"
RESIDENT_CSV_INTERVAL_MINUTES=60
RESIDENT_CSV_ATOMICITY=per_file
```

Setelah pengaturan tersimpan di web, perubahan `.env` untuk tiga nilai ini tidak menimpa pengaturan yang sudah ada.

Pasang dependency dan bangun aset produksi:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Migrasi hanya membuat akun admin otomatis ketika `APP_ENV=local`. Buat akun produksi satu kali lewat Tinker:

```bash
php artisan tinker
```

Di prompt Tinker, buat akun dengan password unik:

```php
App\Models\User::create([
    'name' => 'Administrator',
    'username' => 'admin',
    'email' => 'admin@domain-anda',
    'password' => 'GANTI_DENGAN_PASSWORD_UNIK',
]);
```

Keluar dengan `exit`. Login memakai username dan password di `https://admin.domain-anda/`.

Atur kepemilikan file agar PHP-FPM bisa menulis cache dan log, lalu batasi akses `.env`:

```bash
sudo chown -R "$USER":www-data /var/www/admin_addon_tamu
sudo chmod -R ug+rwX /var/www/admin_addon_tamu/storage /var/www/admin_addon_tamu/bootstrap/cache
sudo chmod 640 /var/www/admin_addon_tamu/.env
```

#### Konfigurasi Nginx

Buat `/etc/nginx/sites-available/admin-addon`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name admin.domain-anda;
    root /var/www/admin_addon_tamu/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan konfigurasi dan cek sintaks:

```bash
sudo ln -s /etc/nginx/sites-available/admin-addon /etc/nginx/sites-enabled/admin-addon
sudo nginx -t
sudo systemctl enable --now nginx php8.3-fpm
sudo systemctl reload nginx
```

Untuk penggunaan produksi, pasang TLS dan gunakan alamat `https://` pada `APP_URL`.

#### Jalankan scheduler dan queue worker

Scheduler memeriksa sinkronisasi setiap menit. Tambahkan cron sebagai user `www-data`:

```bash
sudo crontab -u www-data -e
```

Tambahkan baris berikut:

```cron
* * * * * cd /var/www/admin_addon_tamu && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Worker memproses job di antrean `resident-sync`. Buat `/etc/supervisor/conf.d/admin-addon-worker.conf`:

```ini
[program:admin-addon-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/admin_addon_tamu
command=/usr/bin/php artisan queue:work database --queue=resident-sync --sleep=3 --tries=3 --timeout=180 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=300
redirect_stderr=true
stdout_logfile=/var/log/supervisor/admin-addon-worker.log
numprocs=1
```

Muat dan aktifkan worker:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Tombol **Sinkronkan sekarang** dan jadwal otomatis menggunakan worker ini. Perubahan URL, interval, atau atomicity di web tidak memerlukan restart worker. Setelah ada perubahan kode, restart worker agar memuat kode baru:

```bash
sudo supervisorctl restart 'admin-addon-worker:*'
```

#### Pemeriksaan akhir

```bash
cd /var/www/admin_addon_tamu
php artisan about
php artisan migrate:status
php artisan queue:failed
sudo supervisorctl status
sudo systemctl status nginx php8.3-fpm
```

<div align="center">

<a href="https://github.com/bugihermansyah">
  <img src="https://img.shields.io/badge/GitHub-%40bugihermansyah-181717?style=for-the-badge&amp;logo=github&amp;logoColor=white" alt="GitHub profile: bugihermansyah">
</a>

<br><sub>Dibuat dengan ☕, rasa ingin tahu, dan sedikit debugging tengah malam.</sub>

</div>
