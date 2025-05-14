FROM rabbitmq:3.8-management-alpine

# Habilitar plugins necesarios, incluido Prometheus
RUN rabbitmq-plugins enable --offline rabbitmq_mqtt rabbitmq_federation_management rabbitmq_stomp rabbitmq_prometheus

# Copiar configuración personalizada
COPY rabbitmq.conf /etc/rabbitmq/rabbitmq.conf