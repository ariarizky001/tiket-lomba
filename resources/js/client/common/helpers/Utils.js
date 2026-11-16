import moment from 'moment';

export const formatRupiah = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
};

export const formatAngka = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
  }).format(amount);
};

export const generateInvoiceCode = (eventName, invoiceNumber) => {
  const words = eventName.split(' ');
  const initials = words
    .map((word) => word.charAt(0).toUpperCase())
    .join('')
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, 4);
  const formattedNumber = String(invoiceNumber).padStart(4, '0');
  return `${initials}-${formattedNumber}`;
};

export const sessionName = (item) => {
  const nama = item.session_nama || item.event_session?.nama || item.eventSession?.nama || `Sesi #${item.event_session_id}`;
  const nomor = item.session_nomor || item.event_session?.nomor_urut || item.eventSession?.nomor_urut;
  return nomor ? `${nomor}. ${nama}` : nama;
};

// Date helper functions
export const toMoment = (value) => {
  if (!value) return null;
  if (moment.isMoment(value)) return value;
  const m = moment(value);
  return m.isValid() ? m : null;
};

export const toApiDate = (m) => {
  if (!m) return null;
  if (moment.isMoment(m)) return m.format('YYYY-MM-DD');
  const parsed = moment(m);
  return parsed.isValid() ? parsed.format('YYYY-MM-DD') : null;
};

export const todayMoment = () => moment();

export default { formatRupiah, formatAngka, generateInvoiceCode, sessionName, toMoment, toApiDate, todayMoment };
