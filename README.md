# Crystal File Manager

Advanced file manager that fits in single PHP file.

*a.k.a. Server file manager*

[Ru](README.ru.md) | [En](README.md)

[GitFlick](https://gitflic.ru/project/consensus/cfm) | [GitHub](https://github.com/cfm-group/CFM)

# Functionality
 - **Basic Operations** - Upload/move/delete files, create folders, etc.
 - **Authorisation** - Ability to create and manage user accounts
 - **Easy configuration** - All configuration is done through a graphical interface
 - **Search, sorting and pagination** - Thousands of files won't get in the way of a comfortable workflow.

 # Key Features
 - **Free Licence** (AGPLv3)
 - **No dependencies** (Only built-in PHP functions)
 - **Most of the functionality works without JavaScript**
 - **Modular architecture** (Easy to write plugins)
 - **Delta Updates**
 - **Adapted for mobile devices**
 - **Partial download support**
 - **Availability of Json API**

# Requirements
 - PHP >= 5.5.0
 - Extensions(Built-in): json, iconv, hash

# Installation and Use
## The project is in the development phase
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
php -f build_proj.php cfm
php -S localhost:8080
```
Open `http://localhost:8080/cfm.php` in browser

# TODO
 - WebDAV protocol support
 - System of rights to perform operations
 - Possibility of faking root directory
 - Packing folder into zip archive
 - Automatic updates
 - File content preview
 - Localisation system
 - Tokens for anonymous access to files
 - MIME type override based on file extension
