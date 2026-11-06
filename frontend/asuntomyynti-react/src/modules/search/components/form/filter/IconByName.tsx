import { IconHome, IconLocation, IconProps } from 'hds-react';

type Props = {
  name: string;
} & IconProps;

const IconByName = ({ name, ...rest }: Props) => {
  switch (name) {
    case 'location':
      return <IconLocation {...rest} data-testid="icon-location" />;
    case 'home':
      return <IconHome {...rest} data-testid="icon-home" />;
    default:
      return null;
  }
};

export default IconByName;
