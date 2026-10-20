import { render, screen } from '@testing-library/react';
import InfoBlock from './InfoBlock';
import { FilterName, DataConfig } from '../../types/common';
import { defaultConfig } from '../../modules/search/utils/defaultConfig';

const mockConfig: DataConfig = {
  filters: {
    [FilterName.Price]: defaultConfig(FilterName.Price),
    [FilterName.LivingArea]: defaultConfig(FilterName.LivingArea),
    [FilterName.ProjectBuildingType]: defaultConfig(FilterName.ProjectBuildingType),
    [FilterName.ProjectDistrictHaso]: defaultConfig(FilterName.ProjectDistrictHaso),
    [FilterName.ProjectDistrictHitas]: defaultConfig(FilterName.ProjectDistrictHitas),
    [FilterName.ProjectDistrict]: defaultConfig(FilterName.ProjectDistrict),
    [FilterName.Properties]: defaultConfig(FilterName.Properties),
    [FilterName.RoomCount]: defaultConfig(FilterName.RoomCount),
    [FilterName.StateOfSale]: defaultConfig(FilterName.StateOfSale),
  },
  static_content: {
    haso_instruction_text: 'How applying for haso apartments works ? What is required from the applicant ? Read our buyers guide for haso apartments before proceeding with the application.',
    hitas_instruction_text: 'Miten Hitas-haku etenee? Olemme koonneet tietoa Hitas-asunnoista omalle sivulleen. Tutustuthan siihen ennen kuin jätät hakemuksen.',
    haso_instruction_text_mobile: 'How applying?',
    hitas_instruction_text_mobile: 'How?',
    haso_instruction_icon_text: 'Instructions',
    hitas_instruction_icon_text: 'Ohjeet',
    haso_instruction_url: '/',
    hitas_instruction_url: '/',
  },
  apartment_application_status: {},
  token: 'test',
  user: { user_id: 0, email_address: null, username: '', applications: [] },
} as any;

test('renders InfoBlock for haso', () => {
  render(<InfoBlock config={mockConfig} type={'haso'} />);

  expect(
    screen.getByText(
      'How applying for haso apartments works ? What is required from the applicant ? Read our buyers guide for haso apartments before proceeding with the application.'
    )
  ).toBeDefined();
});

test('renders InfoBlock for hitas', () => {
  render(<InfoBlock config={mockConfig} type={'hitas'} />);

  expect(
    screen.getByText(
      'Miten Hitas-haku etenee? Olemme koonneet tietoa Hitas-asunnoista omalle sivulleen. Tutustuthan siihen ennen kuin jätät hakemuksen.'
    )
  ).toBeDefined();
});
