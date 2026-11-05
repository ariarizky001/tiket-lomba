import React from 'react';
import { useSelector } from 'react-redux';

const Can = ({ permission, children, fallback = null }) => {
  const permissions = useSelector((state) => state.auth.permissions);
  const user = useSelector((state) => state.auth.user);

  if (!permission) {
    return children;
  }

  if (user?.role === 'superadmin') {
    return children;
  }

  if (permissions.includes(permission)) {
    return children;
  }

  return fallback;
};

export default Can;
