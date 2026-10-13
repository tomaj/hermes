# Image thumbnails on RabbitMQ

`public/index.php` stores an uploaded image and emits `image.thumbnails`. The worker reads the queue from RabbitMQ with `LazyRabbitMqDriver` and writes 160, 640 and 1280 px JPEGs to `storage/thumbnails`. Needs `ext-gd`. Docs: <https://tomaj.github.io/hermes/examples/thumbnails>.

```bash
make install
make up                                     # RabbitMQ, management UI on http://localhost:15672 (guest / guest)
make worker                                 # terminal 1
make web                                    # terminal 2
curl -F image=@photo.jpg http://localhost:8000/
ls storage/thumbnails
```

`make e2e` uploads a generated JPEG and fails if the three thumbnails do not appear within 30 seconds.
