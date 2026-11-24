import { Notification } from 'hds-react';
import { useTranslation } from 'react-i18next';

type Props = {
  autoClose?: boolean | undefined;
  dismissible?: boolean | undefined;
  showInline?: boolean | undefined;
  errorMessage?: string | undefined;
};

const ErrorToast = ({ autoClose = true, dismissible = false, showInline = false, errorMessage }: Props) => {
  const { t } = useTranslation();

  return (
    <Notification
      label={t('SEARCH:error')}
      type="error"
      position={showInline ? 'inline' : 'top-right'}
      autoClose={autoClose}
      dismissible={dismissible}
      closeButtonLabelText={'SEARCH:aria-close-notification'}
    >
      {errorMessage ? errorMessage : t('SEARCH:something-wrong')}
    </Notification>
  );
};

export default ErrorToast;
