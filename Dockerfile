FROM rabbitmq:3.8-management-alpine

# Habilitar plugins adicionales
RUN rabbitmq-plugins enable --offline rabbitmq_mqtt rabbitmq_federation_management rabbitmq_stomp rabbitmq_prometheus
