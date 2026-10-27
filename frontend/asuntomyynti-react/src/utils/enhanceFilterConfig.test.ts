import { enhanceFilterConfig } from './enhanceFilterConfig';

describe('enhanceFilterConfig', () => {
  it('returns default shape for known filters', () => {
    const subject = enhanceFilterConfig({});
    expect(subject).toHaveProperty('room_count');
    expect(subject.project_state_of_sale).toHaveProperty('items');
    expect(subject.project_state_of_sale.items).toHaveLength(0);
  });

  it('keeps required properties and excludes unsupported ones', () => {
    const subject = enhanceFilterConfig({
      properties: {
        label: 'Additional selections',
        items: [
          'project_has_sauna',
          'has_apartment_sauna',
          'has_balcony',
          'project_has_elevator',
          'SINGLE_LEVEL',
          'HAS_FRENCH_BALCONY',
        ],
        suffix: null,
      },
    });

    expect(subject.properties.items).toContain('project_has_sauna');
    expect(subject.properties.items).toContain('has_apartment_sauna');
    expect(subject.properties.items).toContain('has_balcony');
    expect(subject.properties.items).toContain('TOP_FLOOR');
    expect(subject.properties.items).toContain('BOTTOM_FLOOR');
    expect(subject.properties.items).toContain('NOT_TOP_FLOOR');
    expect(subject.properties.items).toContain('NOT_BOTTOM_FLOOR');

    expect(subject.properties.items).not.toContain('SINGLE_LEVEL');
    expect(subject.properties.items).not.toContain('HAS_FRENCH_BALCONY');
    expect(subject.properties.items).not.toContain('project_has_elevator');
  });
});
