import axios from 'axios';

const HTTP = axios.create({
  baseURL: '/api',
  withCredentials: true,
});

export default HTTP;
