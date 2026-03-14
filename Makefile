up:        ## Lance le projet (prod)
	docker compose --profile prod up --build -d

dev:       ## Lance le projet (dev, hot-reload frontend)
	docker compose --profile dev up -d

down:      ## Arrête le projet
	docker compose --profile prod --profile dev down

logs:      ## Affiche les logs
	docker compose logs -f

seed:      ## Importe les données depuis Overpass
	docker compose exec backend php bin/console app:import-overpass

clean:     ## Reset complet (supprime les volumes)
	docker compose down -v
