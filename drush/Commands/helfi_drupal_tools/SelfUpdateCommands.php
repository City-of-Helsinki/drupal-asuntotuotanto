<?php

declare(strict_types = 1);

namespace Drush\Commands\helfi_drupal_tools;

use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Process\Process;

/**
 * A Drush commandfile.
 */
final class SelfUpdateCommands extends DrushCommands {

  private const BASE_URL = 'https://raw.githubusercontent.com/City-of-Helsinki/drupal-helfi-platform/main/';

  /**
   * The http client.
   *
   * @var null|\GuzzleHttp\ClientInterface
   */
  private ?ClientInterface $httpClient = NULL;

  /**
   * Gets the http client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The http client.
   */
  private function httpClient() : ClientInterface {
    if (!$this->httpClient) {
      $this->httpClient = new Client(['base_uri' => self::BASE_URL]);
    }
    return $this->httpClient;
  }

  /**
   * Make sure the destination folder exists.
   *
   * @param string $destination
   *   The destination file.
   */
  private function ensureFolder(string $destination) : void {
    $parts = explode('/', $destination);
    array_pop($parts);

    if (count($parts) === 0) {
      return;
    }

    $folder = implode('/', $parts);

    if (!is_dir($folder)) {
      if (!mkdir($folder, 0755, TRUE)) {
        throw new \InvalidArgumentException('Failed to create folder: ' . $folder);
      }
    }
  }

  /**
   * Copies source file to destination.
   *
   * @param string $source
   *   The source.
   * @param string $destination
   *   The destination.
   *
   * @return bool
   *   TRUE if succeeded, FALSE if not.
   */
  private function copyFile(string $source, string $destination) : bool {
    try {
      $this->ensureFolder($destination);
      $resource = Utils::tryFopen($destination, 'w');
      $this->httpClient()->request('GET', $source, ['sink' => $resource]);
    }
    catch (GuzzleException | \InvalidArgumentException $e) {
      $this->io()->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Parses given options.
   *
   * @param array $options
   *   A list of options to parse.
   *
   * @return array
   *   The options.
   */
  private function parseOptions(array $options) : array {
    foreach ($options as $key => $value) {
      // Convert (string) true and false to proper booleans.
      if (is_string($value) && (strtolower($value) === 'true' || strtolower($value) === 'false')) {
        $options[$key] = strtolower($value) === 'true';
      }
    }
    return $options;
  }

  /**
   * Checks if file can be updated.
   *
   * @param string $file
   *   The file.
   *
   * @return bool
   *   TRUE if file can be updated automatically.
   */
  private function fileCanBeUpdated(string $file) : bool {
    $isCI = getenv('CI');

    if ($isCI) {
      // Workflows cannot be updated in CI.
      return !str_starts_with($file, '.github/workflows');
    }
    return TRUE;
  }

  /**
   * Updates files from platform.
   *
   * @param bool $updateDist
   *   Whether to update dist files or not.
   * @param array $map
   *   A list of files to update.
   *
   * @return $this
   *   The self.
   */
  private function updateFiles(bool $updateDist, array $map) : self {
    foreach ($map as $source => $destination) {
      // Fallback source to destination if source is not defined.
      if (is_numeric($source)) {
        $source = $destination;
      }
      // Check if we can update given file. For example, we can't
      // update GitHub workflow files in CI with our current GITHUB_TOKEN.
      // @todo Remove this once we use token with more permissions.
      if (!$this->fileCanBeUpdated($source)) {
        continue;
      }
      $isDist = $this->fileIsDist($source);

      // Update the given dist file only if the original (.dist) file exists and
      // the destination one does not.
      // For example: '.github/workflows/test.yml.dist' should not be added
      // again if '.github/workflows/test.yml' exists unless explicitly told so.
      if ($isDist && (file_exists($source) && !file_exists($destination))) {
        $this->copyFile($source, $source);
        continue;
      }

      // Skip updating .dist files if configured so.
      if (!$updateDist && $isDist) {
        continue;
      }
      if (!$this->copyFile($source, $destination)) {
        throw new \InvalidArgumentException(sprintf('Failed to copy %s to %s', $source, $destination));
      }
    }
    return $this;
  }

  /**
   * Checks if file is dist.
   *
   * @param string $file
   *   The file to check.
   *
   * @return bool
   *   Whether the given file is dist or not.
   */
  private function fileIsDist(string $file) : bool {
    return str_ends_with($file, '.dist');
  }

  /**
   * Removes given file or directory from disk.
   *
   * @param string $source
   *   The source file.
   *
   * @return int
   *   The exit code.
   */
  private function removeFile(string $source) : int {
    $command = is_dir($source) ? 'rmdir' : 'rm';

    if (!file_exists($source)) {
      return 0;
    }
    $process = new Process([$command, $source]);
    return $process->run(function ($type, $buffer) {
      $type === Process::ERR ?
        $this->io()->error($buffer) :
        $this->io()->writeln($buffer);
    });
  }

  /**
   * Creates the given file with given content.
   *
   * @param string $source
   *   The file.
   * @param string|null $content
   *   The content.
   *
   * @return bool
   *   TRUE if file was created, false if not.
   */
  private function createFile(string $source, ?string $content = NULL) : bool {
    if (file_exists($source)) {
      return TRUE;
    }
    return file_put_contents($source, $content) !== FALSE;
  }

  /**
   * Remove old leftover files.
   *
   * @param array $map
   *   A list of files to remove.
   *
   * @return $this
   *   The self.
   */
  private function removeFiles(array $map) : self {
    foreach ($map as $source) {
      if ($this->removeFile($source) !== DrushCommands::EXIT_SUCCESS) {
        throw new \InvalidArgumentException('Failed to remove file: ' . $source);
      }
    }
    return $this;
  }

  /**
   * Adds the given files.
   *
   * @param array $map
   *   A list of files to add.
   *
   * @return $this
   *   The self.
   */
  private function addFiles(array $map) : self {
    foreach ($map as $source => $settings) {
      [
        'remote' => $isRemote,
        'content' => $content,
        'destination' => $destination,
      ] = $settings + [
        'remote' => FALSE,
        'content' => NULL,
        'destination' => NULL,
      ];

      // Copy remote file to given destination.
      if ($isRemote) {
        $destination = $destination ?? $source;

        // Created files should never be updated.
        if (file_exists($destination)) {
          continue;
        }
        $this->copyFile($source, $destination ?? $source);

        continue;
      }

      if (!$this->createFile($source, $content)) {
        throw new \InvalidArgumentException('Failed to create file: ' . $source);
      }
    }
    return $this;
  }

  /**
   * Updates individual files from platform.
   *
   * @param array $files
   *   A comma delimited list of files to update.
   * @param array $options
   *   An array of options.
   *
   * @command helfi:tools:update-platform-files
   *
   * @return int
   *   The exit code.
   */
  public function updatePlatformFiles(array $files, array $options = [
    'update-dist' => TRUE,
  ]) : int {
    if (count($files) < 1) {
      throw new \InvalidArgumentException('You must provide at least one file.');
    }
    $files = StringUtils::csvToArray($files);

    [
      'update-dist' => $updateDist,
    ] = $this->parseOptions($options);

    foreach ($files as $file) {
      [$source, $destination] = explode('=', $file) + [NULL, NULL];

      $destination ? $this->updateFiles($updateDist, [$source => $destination]) :
        $this->updateFiles($updateDist, [0 => $source]);
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Updates the dependencies.
   *
   * @param array $options
   *   The options.
   */
  private function updateExternalPackages(array $options) : void {
    if (empty($options['root'])) {
      throw new \InvalidArgumentException('Missing drupal root.');
    }
    $gitRoot = sprintf('%s/..', rtrim($options['root'], '/'));

    // Update druidfi/tools if the package exists.
    if (is_dir($gitRoot . '/tools')) {
      $this->processManager()->process([
        'make',
        'self-update',
      ])->run(function (string $type, ?string $output) : void {
        $this->io()->write($output);
      });
    }
  }

  /**
   * Updates files from platform.
   *
   * @param bool[] $options
   *   The options.
   *
   * @command helfi:tools:update-platform
   *
   * @return int
   *   The exit code.
   */
  public function updatePlatform(array $options = ['update-dist' => TRUE]) : int {
    [
      'update-dist' => $updateDist,
    ] = $this->parseOptions($options);

    $this->updateExternalPackages($options);

    $this
      ->updateFiles($updateDist, [
        '.github/workflows/test.yml.dist' => '.github/workflows/test.yml',
        '.github/workflows/artifact.yml.dist' => '.github/workflows/artifact.yml',
        '.github/workflows/update-config.yml.dist' => '.github/workflows/update-config.yml',
        '.gitignore.dist' => '.gitignore',
      ])
      ->updateFiles($updateDist, [
        'public/sites/default/azure.settings.php',
        'public/sites/default/settings.php',
        'docker/openshift/custom.locations',
        'docker/openshift/Dockerfile',
        'docker/openshift/entrypoints/20-deploy.sh',
        'docker/openshift/crons/drupal.sh',
        'docker/openshift/crons/migrate-status.php',
        'docker/openshift/crons/migrate-tpr.sh',
        'docker/openshift/crons/prestop-hook.sh',
        'docker/openshift/crons/purge-queue.sh',
        'docker/openshift/crons/update-translations.sh',
        'docker-compose.yml',
        'phpunit.xml.dist',
        'phpunit.platform.xml',
        'tools/make/project/install.mk',
        'tools/make/project/git.mk',
        'tools/commit-msg',
      ])
      ->removeFiles([
        'docker/local/Dockerfile',
        'docker/local/custom.locations',
        'docker/local/entrypoints/30-chromedriver.sh',
        'docker/local/entrypoints/30-drush-server.sh',
        'docker/local/nginx.conf',
        'docker/local/php-fpm-pool.conf',
        'docker/local/',
        'drush/Commands/OpenShiftCommands.php',
      ])
      ->addFiles([
        'public/sites/default/all.settings.php' => [
          'remote' => TRUE,
        ],
      ]);

    return DrushCommands::EXIT_SUCCESS;
  }

}
