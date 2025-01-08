FROM nginx:latest

COPY . /usr/share/nginx/html

ARG BACKEND_URL
RUN sed -i "s_http://localhost:8000_${BACKEND_URL}_" /usr/share/nginx/html/config.js
