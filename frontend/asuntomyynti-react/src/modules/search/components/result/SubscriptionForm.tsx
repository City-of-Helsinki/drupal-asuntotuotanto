import { useEffect, useRef, useState } from 'react';
import { Button, ButtonVariant, Checkbox, Notification, TextInput } from 'hds-react';
import { useTranslation } from 'react-i18next';
import styles from './SubscriptionForm.module.scss';
import axios from 'axios';
import { Project } from '../../../../types/common';
import useSearchParams from '../../../../hooks/useSearchParams';

type Props = {
  onClose?: () => void;
  project: Project;
};

enum FormState {
  InProgress,
  Error,
  Ready,
}

const SubscriptionForm = ({ onClose, project }: Props) => {
  const [email, setEmail] = useState('');
  const [subscribeToNewsLetter, setSubscribeToNewsLetter] = useState(false);
  const [formState, setFormState] = useState(FormState.InProgress);

  const { t } = useTranslation();
  const searchParams = useSearchParams();

  const emailRef = useRef<HTMLInputElement>(null);

  const handleClose = () => {
    if (onClose) {
      onClose();
    }
  };

  const handleSubscribe = async () => {
    try {
      const lang = searchParams.get('lang') || 'fi';
      await axios.post(`/${lang}/mailinglist`, {
        user_email: email,
        project_id: project.id,
        subscribe_mailinglist: subscribeToNewsLetter,
      });
      setFormState(FormState.Ready);
      if (onClose) {
        setTimeout(() => {
          onClose();
        }, 1000);
      }
    } catch {
      setFormState(FormState.Error);
      emailRef.current?.select();
    }
  };

  useEffect(() => {
    emailRef.current?.select();
  }, []);

  const { housing_company, district, street_address } = project;

  return (
    <div>
      <h2 className={styles.header}>{t('SEARCH:save-search-alert')}</h2>
      {formState === FormState.Error && <Notification type="error" label={t('SEARCH:search-alert-error')} />}
      {formState === FormState.Ready && <Notification type="success" label={t('SEARCH:search-alert-saved')} />}
      <div className={styles.fieldWrapper}>
        <p>
          <strong>{housing_company}</strong> {district}, {street_address}
        </p>
      </div>
      <div className={styles.fieldWrapper}>
        <TextInput
          id="email"
          label={t('SEARCH:email')}
          value={email}
          ref={emailRef}
          disabled={formState === FormState.Ready}
          onChange={(event) => {
            setEmail(event.target.value);
            setFormState(FormState.InProgress);
          }}
          helperText={t('SEARCH:search-alert-helper-text')}
        />
      </div>
      <div className={styles.fieldWrapper}>
        <Checkbox
          id="subscribe-newsletter"
          checked={subscribeToNewsLetter}
          onClick={() => setSubscribeToNewsLetter(!subscribeToNewsLetter)}
          label={t('SEARCH:subscribe-to-newsletter')}
        />
      </div>
      <div className={styles.buttonWrapper}>
        <Button onClick={handleClose} variant={ButtonVariant.Secondary}>
          {t('SEARCH:cancel')}
        </Button>
        <Button onClick={handleSubscribe} disabled={formState !== FormState.InProgress}>
          {t('SEARCH:save-search-alert')}
        </Button>
      </div>
    </div>
  );
};

export default SubscriptionForm;
