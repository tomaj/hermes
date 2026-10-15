# Webhook delivery with retries

`public/index.php` emits an `order.shipped` webhook. The worker POSTs it with an HMAC signature. A 5xx or 429 answer throws, so Hermes sends a retry copy with a later `executeAt`. Docs: <https://tomaj.github.io/hermes/examples/webhooks>.

```bash
make install
make up                              # Redis
php -S 127.0.0.1:9000 receiver.php   # terminal 1: answers 503, then 204
make worker                          # terminal 2
php public/index.php                 # terminal 3: emit one webhook
```

The receiver logs attempt 1 at once and attempt 2 16 to 45 seconds later, with the same `Webhook-Id`.

`make e2e` runs the whole flow and fails if the second attempt does not arrive within 60 seconds.
