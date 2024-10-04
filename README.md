# Web Downloads

This project is a collection of scripts to handle downloading builds to the downloads.php.net server

## Set up

- Copy `env.example` to `.env` and set the `AUTH_TOKEN` and `BUILDS_DIRECTORY` values.

- Install dependencies.

```bash
composer install
```

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

## License

[MIT](LICENSE)