{pkgs}: {
  deps = [
    pkgs.chromium
    pkgs.mesa
    pkgs.gtk3
    pkgs.gdk-pixbuf
    pkgs.xorg.libxcb
    pkgs.xorg.libXrandr
    pkgs.xorg.libXfixes
    pkgs.xorg.libXext
    pkgs.xorg.libXdamage
    pkgs.xorg.libXcomposite
    pkgs.xorg.libX11
    pkgs.dbus
    pkgs.cairo
    pkgs.pango
    pkgs.libxkbcommon
    pkgs.libdrm
    pkgs.at-spi2-atk
    pkgs.atk
    pkgs.alsa-lib
    pkgs.cups
    pkgs.nss
    pkgs.nspr
    pkgs.glib
    pkgs.zip
  ];
}
