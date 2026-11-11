import filterApartmentA0 from './filterApartmentA0';

const mockData = [
  {
    street_address: 'Pärinätie 13',
    uuid: '9f79116b-898c-4fa0-80ba-c3870c624372',
    apartments: [
      {
        apartment_address: 'Pärinätie 13 A0',
        apartment_number: 'A0',
        uuid: 'c3145a4e-ef7d-40c5-ad93-f0a164b2704a',
      },
      {
        apartment_address: 'Pärinätie 13 A1',
        apartment_number: 'A1',
        uuid: 'c3145a4e-ef7d-40c5-ad93-f0a164b2704a',
      },
      {
        apartment_address: 'Pärinätie 30 A2',
        apartment_number: 'A2',
        uuid: 'c3145a4e-ef7d-40c5-ad93-f0a164b2704b',
      },
    ],
  },
];

const targetData = [
  {
    street_address: 'Pärinätie 13',
    uuid: '9f79116b-898c-4fa0-80ba-c3870c624372',
    apartments: [
      {
        apartment_address: 'Pärinätie 13 A1',
        apartment_number: 'A1',
        uuid: 'c3145a4e-ef7d-40c5-ad93-f0a164b2704a',
      },
      {
        apartment_address: 'Pärinätie 30 A2',
        apartment_number: 'A2',
        uuid: 'c3145a4e-ef7d-40c5-ad93-f0a164b2704b',
      },
    ],
  },
];

describe('filterApartmentA0', () => {
  it('filters A0 apartment', () => {
    const filteredData = filterApartmentA0(mockData);

    expect(filteredData).toMatchObject(targetData);
  });
});
