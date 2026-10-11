# Registro de puertos Reverb

Los puertos son internos, escuchan únicamente en `127.0.0.1` y Apache publica cada aplicación mediante HTTPS/WSS en su propio VirtualHost.

| Aplicación | Servicio esperado | Puerto interno |
| --- | --- | ---: |
| Inventario Pro | `inventario-pro-reverb.service` | `8081` |
| Floop Publish | servicio Reverb de Floop Publish | `8082` |
| Inventario Family | `inventario-family-reverb.service` | `8083` |

Antes de asignar uno nuevo:

```bash
sudo ss -ltnp | grep -E ':(8080|8081|8082|8083|8084)\b'
sudo systemctl list-units --type=service --all | grep -Ei 'reverb|publish|inventario'
sudo grep -R --line-number -- '--port=808' /etc/systemd/system /lib/systemd/system 2>/dev/null
```

No uses selección dinámica de puertos. Un destino cambiante rompería `ProxyPass` y podría conectar un dominio con la aplicación equivocada después de reiniciar el servidor.
