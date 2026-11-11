<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Generates report text through the configured Drupal AI provider.
 */
final class AiReportTextGenerator implements ReportTextGeneratorInterface {

  /**
   * Creates an AI report text generator.
   */
  public function __construct(
    private readonly AiProviderPluginManager $providerManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ReportPromptBuilder $promptBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function generate(SiteReportContext $context): GeneratedReport {
    $config = $this->configFactory->get('hm_core.ai');
    $providerId = (string) $config->get('provider_id');
    $modelId = (string) $config->get('model_id');
    if ($providerId === '' || $modelId === '') {
      throw new ReportGenerationException('No AI report provider and model are configured.');
    }

    try {
      $provider = $this->providerManager->createInstance($providerId);
      $providerPlugin = $provider->getPlugin();
      if (!$providerPlugin instanceof ChatInterface) {
        throw new ReportGenerationException('The configured AI provider does not support chat.');
      }
      if (!$providerPlugin->isUsable('chat')) {
        throw new ReportGenerationException('The configured AI provider is not ready. Check its API key.');
      }

      $input = new ChatInput([
        new ChatMessage('user', $this->promptBuilder->userPrompt($context)),
      ]);
      $input->setSystemPrompt($this->promptBuilder->systemPrompt());
      $chatCallable = [$provider, 'chat'];
      if (!is_callable($chatCallable)) {
        throw new ReportGenerationException('The AI provider proxy cannot invoke chat.');
      }
      $output = $chatCallable($input, $modelId, ['hm_core', 'client_report']);
      if (!$output instanceof ChatOutput) {
        throw new ReportGenerationException('The AI provider returned an invalid chat response.');
      }
      $message = $output->getNormalized();
      if (!$message instanceof ChatMessage) {
        throw new ReportGenerationException('The AI provider returned an unsupported streamed response.');
      }

      return $this->promptBuilder->parse(
        $message->getText(),
        $providerId . ':' . $modelId,
      );
    }
    catch (ReportGenerationException $exception) {
      throw $exception;
    }
    catch (\Throwable $exception) {
      throw new ReportGenerationException('The AI provider request failed.', 0, $exception);
    }
  }

}
