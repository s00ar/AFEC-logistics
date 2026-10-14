// Preserve account identity unless the administrator explicitly chooses to rename it.
export function accountUpdatePayload(record, values, renameUsername = false) {
  return {
    username: renameUsername ? values.username : record.username,
    display_name: values.display_name ?? record.display_name,
    role: values.role ?? record.role,
    active: values.active ?? Boolean(record.active),
    reason: values.reason,
    version: record.version,
  };
}
