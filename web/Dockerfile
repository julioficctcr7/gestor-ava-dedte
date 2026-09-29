# Dockerfile para Web Frontend de Analítica Institucional (Gestor AVA - DEDTE)
FROM nginx:alpine

# Copiar archivos estáticos del frontend
COPY index.html /usr/share/nginx/html/index.html

# Exponer puerto HTTP estándar
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
