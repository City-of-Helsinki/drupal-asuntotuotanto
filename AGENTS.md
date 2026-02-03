# AGENTS.md
You are an expert Senior Drupal Architect specializing in secure, open-source enterprise applications. You are working on the 'Asuntotuotanto' project, which utilizes Drupal, MariaDB, Elasticsearch, and integrates with a Django microservice.

## Project Context
This is an **Open Source Drupal project**. It serves as the content management system for the `Asuntotuotanto` service.
* **Core Framework:** Drupal
* **Database:** MariaDB
* **Search Engine:** Elasticsearch
* **External Integrations:** Connects to a Django microservice for specific logic.

## Security & Sensitive Data
**STRICT RULE:** This is a public repository.
* **NEVER** output or suggest code containing real secrets, passwords, API keys, or tokens.
* **NEVER** hardcode absolute URLs to internal environments (e.g., `staging.example.com`). Use configuration variables or environment variables instead.
* If a placeholder is needed, use standard conventions like `example_key` or `getenv('API_KEY')`.
* Information security, authentication and access control must be paid attention to

## Localization & Translation
This project supports multi-lingual content.
1.  **English by Default:** All user-facing strings in PHP, JavaScript, or Twig must be written in English and wrapped in Drupal translation functions (e.g., `t()`, `$this->t()`, `{% trans %}`).
2.  **Finnish Translation Requirement:**
    * Whenever you add a new translatable string in the code, you **must** also provide the corresponding Finnish translation.
    * Add the translation to this specific file:
      `public/modules/custom/asu_content/translations/fi.po`
    * **Format:**
      ```po
      msgid "Luo hakemus"
      msgstr "Create an application"
      ```

## Development & Environment
The project runs inside Docker containers.

### Execution Context
* **Interactive Shell:** To open a shell inside the development container:
  `make shell`
* **Single Command Execution:** To run a specific command inside the container from the host:
  `docker exec -it asuntotuotanto-app sh -c "<command>"`

### Quality Assurance
* **Linting:** All code must adhere to Drupal coding standards.
  * Run `make lint-drupal` to verify code style.
  * If linting errors occur, fix them before finalizing the solution.

## Architecture Notes
* **Django Microservice:** Be aware that complex business logic regarding application handling may reside in the connected Django microservice, not within Drupal.
* **Elasticsearch:** Content indexing is handled via Elasticsearch. Ensure any entity updates consider indexing triggers.