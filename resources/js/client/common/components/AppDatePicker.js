import React from 'react';
import { DatePicker } from 'antd';
import moment from 'moment';

const AppDatePicker = (props) => {
  const {
    value,
    onChange,
    format = 'DD/MM/YYYY',
    placeholder = 'Pilih tanggal',
    allowClear = true,
    inputReadOnly = true,
    getPopupContainer = (trigger) => trigger?.parentNode ?? document.body,
    dropdownClassName = 'latber-datepicker-dropdown',
    disabled,
    disabledDate,
    ...restProps
  } = props;

  const handleChange = (date, dateString) => {
    if (onChange) {
      if (!date) {
        onChange(null, '');
      } else if (moment.isMoment(date)) {
        onChange(date, date.format(format));
      } else {
        const m = moment(date);
        if (m.isValid()) {
          onChange(m, m.format(format));
        }
      }
    }
  };

  const handleOpenChange = (open) => {
    if (props.onOpenChange) {
      props.onOpenChange(open);
    }
  };

  const safeGetPopupContainer = (trigger) => {
    try {
      const container = getPopupContainer(trigger);
      return container || document.body;
    } catch (e) {
      return document.body;
    }
  };

  return (
    <DatePicker
      value={value ? (moment.isMoment(value) ? value : moment(value)) : null}
      onChange={handleChange}
      onOpenChange={handleOpenChange}
      format={format}
      placeholder={placeholder}
      allowClear={allowClear}
      inputReadOnly={inputReadOnly}
      getPopupContainer={safeGetPopupContainer}
      dropdownClassName={dropdownClassName}
      disabled={disabled}
      disabledDate={disabledDate}
      {...restProps}
    />
  );
};

export default AppDatePicker;