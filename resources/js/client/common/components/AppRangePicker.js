import React from 'react';
import { DatePicker } from 'antd';
import moment from 'moment';

const { RangePicker } = DatePicker;

const AppRangePicker = (props) => {
  const {
    value,
    onChange,
    format = 'DD/MM/YYYY',
    placeholder = ['Mulai', 'Sampai'],
    allowClear = true,
    inputReadOnly = true,
    getPopupContainer = (trigger) => trigger?.parentNode ?? document.body,
    dropdownClassName = 'latber-datepicker-dropdown',
    disabled,
    disabledDate,
    ...restProps
  } = props;

  const handleChange = (dates, dateStrings) => {
    if (onChange) {
      if (!dates || !dates[0] || !dates[1]) {
        onChange(null, ['', '']);
      } else if (moment.isMoment(dates[0]) && moment.isMoment(dates[1])) {
        onChange(dates, [dates[0].format(format), dates[1].format(format)]);
      } else {
        const start = dates[0] ? moment(dates[0]) : null;
        const end = dates[1] ? moment(dates[1]) : null;
        if (start?.isValid() && end?.isValid()) {
          onChange([start, end], [start.format(format), end.format(format)]);
        }
      }
    }
  };

  const handleOpenChange = (open) => {
    if (props.onOpenChange) {
      props.onOpenChange(open);
    }
  };

  const handleFocus = (e) => {
    if (props.onFocus) {
      props.onFocus(e);
    }
    e.stopPropagation();
    e.preventDefault();
  };

  const handleClick = (e) => {
    if (props.onClick) {
      props.onClick(e);
    }
    e.stopPropagation();
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
    <RangePicker
      value={value && value[0] && value[1]
        ? [moment.isMoment(value[0]) ? value[0] : moment(value[0]), moment.isMoment(value[1]) ? value[1] : moment(value[1])]
        : null}
      onChange={handleChange}
      onOpenChange={handleOpenChange}
      onFocus={handleFocus}
      onClick={handleClick}
      format={format}
      placeholder={placeholder}
      allowClear={allowClear}
      inputReadOnly={inputReadOnly}
      getPopupContainer={(trigger) => {
        try {
          const container = getPopupContainer(trigger);
          return container || document.body;
        } catch (e) {
          return document.body;
        }
      }}
      dropdownClassName={dropdownClassName}
      disabled={disabled}
      disabledDate={disabledDate}
      {...restProps}
    />
  );
};

export default AppRangePicker;