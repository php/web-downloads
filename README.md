# Web Downloads

This project handles downloading builds to the downloads.php.net server.

It supports the following type of builds:

- PHP
- PECL extensions
- Winlibs libraries

It also has commands to process the downloaded files and update the relevant configuration files.

## Apache configuration

- Set up a virtual host in Apache to point to the `public` directory as the `DocumentRoot`.

- Set up the `ErrorDocument` for 404 to point to `public/redirect.php` in the virtual host configuration.

- Set up the following rewrite rules in the virtual host configuration:

```apache
<Directory "/path/to/public/directory">
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:Authorization} .
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>
</Directory>
```

## Code requirements

Code must function on a vanilla PHP 8.2 installation.
Please keep this in mind before filing a pull request.

## API
For API documentation, please refer to [API.md](API.md).

## License

[MIT](LICENSE)
