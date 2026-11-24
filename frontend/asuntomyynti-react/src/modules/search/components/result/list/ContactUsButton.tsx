import { useTranslation } from 'react-i18next';
import { Apartment } from "../../../../../types/common";
import css from './ContactUsButton.module.scss';

type Props = {
    href: string;
    apartment: Apartment
    isDesktopSize: boolean;
};

const ContactUsButton = ({ href, apartment, isDesktopSize }: Props) => {
    const { t } = useTranslation();

    return (
            <a
              href={href}
              className={`${css.contactUsButton} hds-button hds-button--${
                isDesktopSize ? 'secondary' : 'primary'
              } hds-button--small`}
            >
              <span className="hds-button__label">
                {t('SEARCH:contact-us')}
                <span className="sr-only">
                  , {t('SEARCH:apartment')} {apartment.apartment_number}
                </span>
              </span>
            </a>
          )
}

export default ContactUsButton;