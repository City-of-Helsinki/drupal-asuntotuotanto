import { Apartment } from '../../../types/common';
import formattedPrice from './formatPrice';

export const getApartmentPrice = (apartment: Apartment): string => {
  if (!apartment) return '-';

  const { debt_free_sales_price, right_of_occupancy_payment, release_payment, project_ownership_type } = apartment;

  if (project_ownership_type.toLowerCase() === 'haso') {
    if (release_payment && release_payment > 0) {
      return formattedPrice(release_payment);
    }
    if (right_of_occupancy_payment) {
      return formattedPrice(right_of_occupancy_payment);
    }
    return '-';
  }

  return debt_free_sales_price ? formattedPrice(debt_free_sales_price) : '-';
};
