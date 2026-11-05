import HTTP from '../../../common/helpers/HTTP';

const INITIAL_STATE = {
  user: null,
  permissions: [],
  ready: false,
};

const AUTH_SET_USER = 'AUTH_SET_USER';
const AUTH_SET_READY = 'AUTH_SET_READY';
const AUTH_LOGOUT = 'AUTH_LOGOUT';
const AUTH_SET_PERMISSIONS = 'AUTH_SET_PERMISSIONS';

export const authReducer = (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case AUTH_SET_USER:
      return {
        ...state,
        user: action.payload,
        permissions: action.payload.permissions || [],
        ready: true,
      };
    case AUTH_SET_PERMISSIONS:
      return {
        ...state,
        permissions: action.payload,
      };
    case AUTH_SET_READY:
      return {
        ...state,
        ready: true,
      };
    case AUTH_LOGOUT:
      return {
        ...state,
        user: null,
        permissions: [],
      };
    default:
      return state;
  }
};

export const initAuth = () => (dispatch) => {
  const token = localStorage.getItem('token');
  if (token) {
    return HTTP.get('/me')
      .then((res) => {
        dispatch({ type: AUTH_SET_USER, payload: res.data });
      })
      .catch(() => {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        dispatch({ type: AUTH_SET_READY });
      });
  }
  dispatch({ type: AUTH_SET_READY });
  return Promise.resolve();
};

export const fetchMe = () => (dispatch) => {
  HTTP.get('/me')
    .then((res) => {
      dispatch({ type: AUTH_SET_USER, payload: res.data });
    })
    .catch(() => {
      dispatch({ type: AUTH_SET_READY });
    });
};

export const login = (email, password) => (dispatch) => {
  return HTTP.post('/login', { email, password })
    .then((res) => {
      const { token, permissions, ...user } = res.data;
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      dispatch({ type: AUTH_SET_USER, payload: { ...user, permissions } });
      return { ...user, permissions };
    })
    .catch((err) => {
      throw err;
    });
};

export const logout = () => (dispatch) => {
  return HTTP.post('/logout')
    .then(() => {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      dispatch({ type: AUTH_LOGOUT });
    });
};

export default authReducer;
