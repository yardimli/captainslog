use serde::Serialize;
use std::time::{SystemTime, UNIX_EPOCH};

#[derive(Clone, Debug, Serialize)]
pub struct ActivitySnapshot {
    pub application: String,
    pub executable: String,
    pub window_title: String,
    pub process_id: u32,
    pub idle_seconds: u64,
    pub captured_at_unix_ms: u128,
    pub supported: bool,
}

pub fn capture() -> ActivitySnapshot {
    #[cfg(target_os = "windows")]
    let (application, executable, window_title, process_id, idle_seconds) = windows::capture();

    #[cfg(not(target_os = "windows"))]
    let (application, executable, window_title, process_id, idle_seconds) = (
        "Unsupported platform".to_owned(),
        String::new(),
        String::new(),
        0,
        0,
    );

    ActivitySnapshot {
        application,
        executable,
        window_title,
        process_id,
        idle_seconds,
        captured_at_unix_ms: SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap_or_default()
            .as_millis(),
        supported: cfg!(target_os = "windows"),
    }
}

#[cfg(target_os = "windows")]
mod windows {
    use std::{ffi::OsString, os::windows::ffi::OsStringExt, path::Path};
    use windows_sys::Win32::{
        Foundation::CloseHandle,
        System::{
            SystemInformation::GetTickCount,
            Threading::{
                OpenProcess, QueryFullProcessImageNameW, PROCESS_QUERY_LIMITED_INFORMATION,
            },
        },
        UI::{
            Input::KeyboardAndMouse::{GetLastInputInfo, LASTINPUTINFO},
            WindowsAndMessaging::{
                GetForegroundWindow, GetWindowTextLengthW, GetWindowTextW, GetWindowThreadProcessId,
            },
        },
    };

    pub fn capture() -> (String, String, String, u32, u64) {
        unsafe {
            let hwnd = GetForegroundWindow();
            if hwnd.is_null() {
                return (
                    "Desktop".into(),
                    String::new(),
                    String::new(),
                    0,
                    idle_seconds(),
                );
            }

            let title_length = GetWindowTextLengthW(hwnd);
            let mut title_buffer = vec![0u16; (title_length.max(0) + 1) as usize];
            let copied = GetWindowTextW(hwnd, title_buffer.as_mut_ptr(), title_buffer.len() as i32);
            let window_title = wide_string(&title_buffer[..copied.max(0) as usize]);

            let mut process_id = 0u32;
            GetWindowThreadProcessId(hwnd, &mut process_id);
            let executable = executable_path(process_id);
            let application = Path::new(&executable)
                .file_stem()
                .and_then(|name| name.to_str())
                .unwrap_or("Unknown application")
                .to_owned();

            (
                application,
                executable,
                window_title,
                process_id,
                idle_seconds(),
            )
        }
    }

    unsafe fn executable_path(process_id: u32) -> String {
        let process = OpenProcess(PROCESS_QUERY_LIMITED_INFORMATION, 0, process_id);
        if process.is_null() {
            return String::new();
        }
        let mut buffer = vec![0u16; 32_768];
        let mut length = buffer.len() as u32;
        let succeeded = QueryFullProcessImageNameW(process, 0, buffer.as_mut_ptr(), &mut length);
        CloseHandle(process);
        if succeeded == 0 {
            String::new()
        } else {
            wide_string(&buffer[..length as usize])
        }
    }

    unsafe fn idle_seconds() -> u64 {
        let mut input = LASTINPUTINFO {
            cbSize: std::mem::size_of::<LASTINPUTINFO>() as u32,
            dwTime: 0,
        };
        if GetLastInputInfo(&mut input) == 0 {
            return 0;
        }
        (GetTickCount().wrapping_sub(input.dwTime) / 1000) as u64
    }

    fn wide_string(value: &[u16]) -> String {
        OsString::from_wide(value).to_string_lossy().into_owned()
    }
}
