# Crystal File Manager
## The project is in the development phase

Advanced file manager that fits in 1 PHP file.

[Ru](README.ru.md) | [En](README.md)

# Functionality:
 - **Basic Operations** - Upload/move/delete files, create folders, etc.
 - **Authorisation** - Ability to create and manage user accounts
 - **Search, sorting and pagination** - Thousands of files won't get in the way of a comfortable workflow.
 - **Mobile adaptability** - Access your files on the go.
 - **Partial download support** - You can download a file if your download is interrupted.

# Ключевые особенности:
 - **Свободная лицензия** (AGPLv3)
 - **Никаких зависимостей** (Только встроенные в PHP функции)
 - **Полная функциональность без JavaScript**
 - **Модульная архитектура** (Простота написания плагинов)
 - **Возможность дельта-обновлений**

 # Key Features:
 - **Free Licence** (AGPLv3)
 - **No dependencies** (Only built-in PHP functions)
 - **Full functionality without JavaScript**
 - **Modular architecture** (Easy to write plugins)
 - **Delta Updates**

# Requirements:
 - PHP >= 5.5.0
 - Extensions(Built-in): json, iconv, hash

# Installation and Use:
```bash
wget https://raw.githubusercontent.com/trashlogic/CFM/refs/heads/master/cfm.php
php -S localhost:8080
```
Open `http://localhost:8080/cfm.php` in browser

# Finansial Support
 - [Boosty](https://boosty.to/trashlogic/donate)
 - ~~Open Collectvie~~ (Temporarily unavailable)

# Build and test
To test changes use `runtime_cfm.php`

```bash
git clone https://github.com/cfm-group/CFM
php -f build_cfm.php ./cfm-canary.php
php -S localhost:8080
```
Open `http://localhost:8080/cfm-canary.php` in browser

# TODO:
 - WebDAV protocol support
 - System of rights to perform operations
 - Possibility of faking root directory
 - Packing folder into zip archive
 - Automatic updates
 - File content preview
 - Localisation system
