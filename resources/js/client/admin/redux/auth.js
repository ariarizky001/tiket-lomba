import HTTP from '../../../common/helpers/HTTP';

const INITIAL_STATE = {
  user: null,
  ready: false,
};

const AUTH_SET_USER = 'AUTH_SET_USER';
const AUTH_SET_READY = 'AUTH_SET_READY';
const AUTH_LOGOUT = 'AUTH_LOGOUT';

export const authReducer = (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case AUTH_SET_USER:
      return {
        ...state,
        user: action.payload,
        ready: true,
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
      };
    default:
      return state;
  }
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
      dispatch({ type: AUTH_SET_USER, payload: res.data });
      return res.data;
    })
    .catch((err) => {
      throw err;
    });
};

export const logout = () => (dispatch) => {
  return HTTP.post('/logout')
    .then(() => {
      dispatch({ type: AUTH_LOGOUT });
    });
};

export default authReducer;
