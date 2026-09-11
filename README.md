# Antojos Paisas

Aplicación web para la operación de un negocio de comidas rápidas. Reúne la tienda pública y las herramientas internas para registrar pedidos, administrar el menú y consultar la operación diaria.

## Funcionalidades

- Catálogo de productos y adicionales.
- Carrito y toma de pedidos desde la tienda.
- Registro de clientes, mesas y pedidos para llevar.
- Gestión interna de pedidos y estados.
- Caja, ventas, gastos, compras y reportes.
- Integración opcional con ePayco mediante variables de entorno.

## Tecnologías

- PHP y Apache.
- MySQL/MariaDB mediante PDO.
- JavaScript y CSS en `app/assets`.
- Docker Compose.

## Inicio

1. Copia `.env.example` a `.env` y completa los valores del entorno.
2. Ajusta el servicio MySQL indicado en `docker-compose.yml`.
3. Ejecuta `docker compose up --build`.

## Estructura

- `app/`: aplicación PHP, vistas, endpoints y activos.
- `app/assets/`: estilos, scripts, logo y favicon.
- `Dockerfile` y `docker-compose.yml`: ejecución del servicio.

No se incluyen credenciales, ventas, clientes, cargas ni bases de datos. Mantén `.env` fuera de Git y utiliza HTTPS en producción.
