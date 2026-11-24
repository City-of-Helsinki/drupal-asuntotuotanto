import { useRef } from 'react';
import styles from './Modal.module.scss';
import ReactDOM from 'react-dom';
import useOutsideClick from '../../hooks/useOutsideClick';

type Props = {
  isVisible?: boolean;
  setVisible?: (isVisible: boolean) => void;
  children: JSX.Element;
};

const Modal = ({ isVisible = false, setVisible, children }: Props) => {
  const ref = useRef<HTMLDivElement>(null);

  useOutsideClick(ref, () => {
    if (setVisible) {
      setVisible(false);
    }
  });

  if (!isVisible) {
    return null;
  }

  // Render the modal to document root
  return ReactDOM.createPortal(
    <div className={styles.overlay}>
      <div className={styles.container} ref={ref}>
        {children}
      </div>
    </div>,
    document.body
  );
};

export default Modal;
