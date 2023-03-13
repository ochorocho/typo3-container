variable "php_version" {
  type    = string
  default = "8.1"
}

variable "php_modules" {
  type    = string
  default = "intl opcache"
}

variable "typo3_version" {
  type    = string
  default = "dev-main"
}

variable "php_ext_configure" {
  type    = string
  default = "docker-php-ext-configure gd --with-freetype --with-jpeg"
}

variable "tags" {
  type = list(string)
  default = ["dev-main"]
}

packer {
  required_plugins {
    docker = {
      version = ">= 0.0.7"
      source = "github.com/hashicorp/docker"
    }
  }
}

source "docker" "arm64" {
  image = "php:${var.php_version}-apache"
  commit = true
  platform = "linux/arm64"
}

source "docker" "amd64" {
  image = "php:${var.php_version}-apache"
  commit = true
  platform = "linux/amd64"
}

build {
  name    = "ochorocho/typo3-container"
  sources = [
    "source.docker.arm64",
    "source.docker.amd64"
  ]

  provisioner "shell" {
    inline = [
      "mkdir -p /etc/ImageMagick-6"
    ]
  }

  provisioner "file" {
    source = "config/apache-typo3.conf"
    destination = "/etc/apache2/sites-available/000-default.conf"
  }

  provisioner "file" {
    source = "config/php.ini"
    destination = "/usr/local/etc/php/conf.d/php.ini"
  }

  provisioner "file" {
    source = "config/imagemagick-policy.xml"
    destination = "/etc/ImageMagick-6/policy.xml"
  }

  provisioner "file" {
    source = "config/composer.json"
    destination = "/var/www/html/composer.json"
  }

  provisioner "shell" {
    inline = [
      "apt-get update && apt-get install --no-install-recommends -y -o Dpkg::Options::='--force-confold' gnupg ca-certificates lsb-release imagemagick ghostscript libcurl4 locales-all unzip libzip4 libpq-dev libzip-dev libcurl4-openssl-dev libpng-dev libwebp-dev libjpeg62-turbo-dev libreadline-dev libicu-dev libonig-dev libfreetype6-dev libxml2-dev apt-utils && ${var.php_ext_configure} && a2enmod alias authz_core autoindex deflate expires filter headers setenvif rewrite && docker-php-ext-install ${var.php_modules} && a2ensite 000-default.conf && rm -rf /var/cache/apt/* /var/cache/debconf/* /var/log/dpkg.log && apt-get --purge -y remove gcc cpp g++-10 icu-devtools libbrotli-dev libncurses-dev libstdc++-10-dev make binutils cpp-10 linux-libc-dev && apt-get -y autoremove",
      "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer"
    ]
  }

  post-processors {
    post-processor "docker-tag" {
      repository =  "ochorocho/typo3-container"
      tags = ["dev-main"]
    }
    post-processor "docker-push" {
      login=true
      login_username = var.docker_username
      login_password = var.docker_password
    }
  }
}
