# Gestor AVA - Módulo de Analítica Académica Institucional (DEDTE - UAGRM)

> **Universidad Autónoma Gabriel René Moreno (U.A.G.R.M.)**  
> **Departamento de Educación a Distancia y Tecnología Educativa (DEDTE)**  
> **Sustento Normativo**: Estatuto Orgánico de la UAGRM (Art. 39 incisos f y g, Art. 128 inciso c) y **Resolución ICU N° 053/2023**.  
> **Repositorio Oficial**: [https://github.com/julioficctcr7](https://github.com/julioficctcr7)  
> **Cuenta Google Cloud**: `ficct.cr7@gmail.com`

---

## 🏛️ Arquitectura del Sistema: Módulo Desacoplado CQRS

El sistema implementa el patrón **CQRS (Command Query Responsibility Segregation)** para garantizar máxima velocidad y **cero bloqueos** sobre el motor transaccional de Moodle:

```
[ Moodle LMS OLTP (:8080) ] ────(Transacciones de Cursos/Notas)────> [ MariaDB Primary ]
                                                                             │
                                                                   (Replicación Async)
                                                                             ▼
[ Gestor AVA Analytics (:8085) ] ──(Lecturas CQRS <15ms SELECT)────> [ Replica Read-Only ]
```

### Componentes:
1. **`backend/`**: Motor REST de analítica en **PHP 8.2-FPM** con OPcache precompilado en memoria y optimizado para latencias menores a 15 ms.
2. **`web/`**: Interfaz de toma de decisiones réplica del Gestor AVA oficial (`ava.uagrm.edu.bo`), construida con **TailwindCSS v3.3.3**, diseño responsive, selectores de plataforma (Virtual vs Presencial), simulador de roles institucionales y navegación Drill-Down jerárquica.
3. **`infra/`**: Configuración de contenedores con Docker Compose y proxy inverso Nginx con compresión Gzip, HTTP/2 y caching de assets estáticos.
4. **`.github/workflows/deploy.yml`**: Pipeline CI/CD automatizado hacia **Google Cloud Platform (GCP)**.

---

## 🚦 Indicadores Normativos (Resolución ICU N° 053/2023)

1. **Segunda Instancia (Repechaje)**:
   - Condición estricta: $40 \le \text{Calificación Final} \le 50$ puntos.
   - Cálculo automático y reporte nominal para autoridades académicas.
2. **Semáforo de Asignaturas Críticas**:
   - 🔴 **Rojo (Crítico)**: Tasa de reprobación $> 40\%$ o Deserción $> 25\%$.
   - 🟡 **Amarillo (Alerta)**: Reprobación entre $20\%$ y $40\%$.
   - 🟢 **Verde (Óptimo)**: Reprobación $< 20\%$.
3. **Control de Inactividad de Estudiantes**:
   - ⚠️ **Nivel 1**: Sin acceso por más de 7 días continuos.
   - 🚨 **Nivel 2**: Sin acceso por más de 14 días o sin entregas de actividades evaluativas.

---

## ☁️ Despliegue en Google Cloud Platform (GCP)

- **Región**: `southamerica-west1` (Santiago de Chile) para latencia ultrabaja hacia Bolivia (< 45 ms).
- **Servicios**:
  - **Google Compute Engine (GCE)**: VM `e2-standard-2` con Ubuntu 22.04 LTS.
  - **Google Artifact Registry**: `southamerica-west1-docker.pkg.dev/PROYECTO/uagrm-dedte-repo`.
  - **Cloud Firewall**: Puertos 80 (HTTP) y 443 (HTTPS) habilitados.

### Secretos requeridos en GitHub Actions (`Settings -> Secrets and variables -> Actions`):
- `GCP_PROJECT_ID`: ID del proyecto en GCP (`ficct.cr7@gmail.com`).
- `GCP_SA_KEY`: Clave JSON de la cuenta de servicio con roles `Artifact Registry Administrator` y `Compute Admin`.
- `GCE_INSTANCE_NAME`: Nombre de la instancia VM (ej. `dedte-analitica-prod`).
- `VM_SSH_HOST`: IP pública de la máquina virtual.
- `VM_SSH_USER`: Usuario SSH en la máquina virtual (ej. `deployer` o `ubuntu`).
- `VM_SSH_PRIVATE_KEY`: Clave privada SSH para acceso automatizado.

---

## 🚀 Ejecución Local con Docker Compose

```bash
cd infra
docker compose up -d --build
```
- Acceso al Gestor AVA Analítica: [http://localhost:8085](http://localhost:8085)
- Acceso a la API CQRS: [http://localhost:8085/api/metrics.php?action=macro_summary](http://localhost:8085/api/metrics.php?action=macro_summary)
