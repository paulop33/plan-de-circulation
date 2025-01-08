FROM python:3.10-slim

# Installer les dépendances système nécessaires à GDAL
RUN apt-get update \
 && apt-get install -y \
      gdal-bin \
      libgdal-dev \
      postgresql-client \
 && apt-get clean

# Définir une variable d'environnement pour GDAL
ENV CPLUS_INCLUDE_PATH=/usr/include/gdal
ENV C_INCLUDE_PATH=/usr/include/gdal

# Installer les dépendances
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copier le code
COPY . .

# Exposer l'application FastAPI
ARG PORT=80
ENV PORT=${PORT}
CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port ${PORT}"]
