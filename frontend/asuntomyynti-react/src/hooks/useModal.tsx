import { useCallback, useState } from 'react';
import Modal from '../common/modal/Modal';

type Props = {
  children: JSX.Element;
};

const useModal = () => {
  const [isVisible, setVisible] = useState(false);

  const handleOpenModal = useCallback(() => {
    setVisible(true);
  }, []);

  const handleCloseModal = useCallback(() => {
    setVisible(false);
  }, []);

  return {
    isOpen: isVisible,
    openModal: handleOpenModal,
    closeModal: handleCloseModal,
    Modal: ({ children }: Props) => (
      <Modal isVisible={isVisible} setVisible={setVisible}>
        {children}
      </Modal>
    ),
  };
};

export default useModal;
