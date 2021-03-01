<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr\Plugin\Field\FieldFormatter\ServiceMapFormatter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests service map formatter field formatter.
 *
 * @coversDefaultClass \Drupal\helfi_tpr\Plugin\Field\FieldFormatter\ServiceMapFormatter
 * @group helfi_tpr
 */
class ServiceMapFormatterTest extends UnitTestCase {

  /**
   * The service map formatter.
   *
   * @var \Drupal\helfi_tpr\Plugin\Field\FieldFormatter\ServiceMapFormatter
   */
  protected ServiceMapFormatter $sut;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected FieldDefinitionInterface $fieldDefinition;

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    // Mock the field type manager and place it in the container.
    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $this->prophesize(FieldTypePluginManagerInterface::class)->reveal());
    \Drupal::setContainer($container);

    $this->fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $this->sut = new ServiceMapFormatter('service_map_embed', [], $this->fieldDefinition, [], 'hidden', 'full', []);
  }

  /**
   * Tests with invalid entity type.
   */
  public function testInvalidEntity() : void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "service_map_embed" field can only be used with tpr_unit entities.');
    $list = $this->prophesize(FieldItemListInterface::class);
    $list->getEntity()->willReturn($this->prophesize(RemoteEntityBase::class)->reveal());
    $this->sut->viewElements($list->reveal(), 'en');
  }

  /**
   * Tests render array.
   */
  public function testRenderArray() : void {
    $entity = $this->prophesize(Unit::class);
    $entity->id()->willReturn('1');
    $parent = $this->prophesize(TraversableTypedDataInterface::class);
    $parent->onChange('service_map_embed')->shouldBeCalled();
    $parent->getValue()->willReturn($entity->reveal());
    $list = FieldItemList::createInstance($this->fieldDefinition, 'service_map_embed', $parent->reveal());
    $list->setValue('test');

    $result = $this->sut->viewElements($list, 'en');
    $this->assertCount(1, $result);

    $this->assertEquals([
      'src' => 'https://palvelukartta.hel.fi/embed/unit/1',
      'frameborder' => 0,
      'title' => 'Service map',
    ], $result[0]['iframe']['#attributes']);

    $this->assertEquals([
      'href' => 'https://palvelukartta.hel.fi/unit/1',
      'target' => TRUE,
    ], $result[0]['link']['#attributes']);

    $this->sut->setSetting('iframe_title', 'Iframe title changed');
    $this->sut->setSetting('link_title', 'Link title changed');
    $this->sut->setSetting('target', FALSE);
    $result = $this->sut->viewElements($list, 'en');

    $this->assertFalse($result[0]['link']['#attributes']['target']);
    $this->assertEquals('Iframe title changed', $result[0]['iframe']['#attributes']['title']);
    $this->assertEquals('Link title changed', $result[0]['link']['#value']);
  }

}
