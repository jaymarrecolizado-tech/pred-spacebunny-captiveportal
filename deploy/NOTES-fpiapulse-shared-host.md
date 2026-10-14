# Production notes — fpiapulse.dictr2.cloud (shared CyberPanel host)

The vhost docroot is the site directory and `/etc/nginx` is root-only, so the
app lives at `htdocs/.fpiapulse-app` (outside the web root) with only a front
controller and symlinks inside the docroot. If any of these symlinks go missing
(after a panel rebuild, for example), the symptom is an unstyled or dead page:

```
cd ~/htdocs/fpiapulse.dictr2.cloud
for d in build css js fonts vendor robots.txt favicon.ico storage; do
  ln -sfn "../.fpiapulse-app/public/$d" "$d"
done
```

Two gotchas specific to this host:

1. **The vhost serves `*.js`/`*.css` as static files** and returns nginx's own 404
   for missing ones without reaching Laravel. Livewire v4 serves its JS from a
   route, so after every `composer update` that touches `livewire/livewire`, run:
   `php artisan livewire:publish --assets`
   (this switches Livewire to static files under `public/vendor/livewire/`).
   Filament assets need no step — they are already files under `public/`.
2. **TLS is issued in the panel** (SSL → Let's Encrypt), not via certbot on the CLI.
