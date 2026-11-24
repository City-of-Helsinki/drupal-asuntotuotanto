import { useTranslation } from 'react-i18next';
import { Apartment } from '../../../../../types/common';
import type { ApplicationCtaVariant } from '../../../utils/applicationFlags';
import css from './CreateApplicationButton.module.scss';

type Props = {
  href: string;
  apartment: Apartment;
  variant: ApplicationCtaVariant;
};

const LABEL_KEYS: Record<ApplicationCtaVariant, string> = {
  apply: 'SEARCH:apply',
  'after-apply': 'SEARCH:after-apply',
  'make-reservation': 'SEARCH:make-reservation',
};

const CreateApplicationButton = ({ href, apartment, variant }: Props) => {
  const { t } = useTranslation();

  return (
    <a href={href} className={`${css.createApplicationButton} hds-button hds-button--primary hds-button--small`}>
      <span className="hds-button__label">
        {t(LABEL_KEYS[variant])}
        <span className="sr-only">
          , {t('SEARCH:apartment')} {apartment.apartment_number}
        </span>
      </span>
    </a>
  );
};

export default CreateApplicationButton;
export type { ApplicationCtaVariant as CreateApplicationButtonVariant };
