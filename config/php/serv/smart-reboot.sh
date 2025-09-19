# #!/bin/bash
# # Fichier : /root/smart-reboot.sh

# LOG="/var/log/server-reboot.log"
# THRESHOLD=85
# NOW=$(date '+%Y-%m-%d %H:%M:%S')

# # Vérifie si le système demande un redémarrage
# if [ -f /var/run/reboot-required ]; then
#     echo "[$NOW] Redémarrage requis par le système." >> $LOG
#     /sbin/shutdown -r now
#     exit
# fi

# # Vérifie la présence d'un fichier trigger
# if [ -f /root/force-reboot ]; then
#     echo "[$NOW] Redémarrage forcé par trigger." >> $LOG
#     rm -f /root/force-reboot
#     /sbin/shutdown -r now
#     exit
# fi

# # Vérifie l'utilisation de la RAM
# USED_RAM=$(free | awk '/Mem:/ {printf("%.0f", $3/$2 * 100)}')

# if [ "$USED_RAM" -ge "$THRESHOLD" ]; then
#     echo "[$NOW] RAM utilisée à $USED_RAM% — redémarrage." >> $LOG
#     /sbin/shutdown -r now
# else
#     echo "[$NOW] RAM OK ($USED_RAM%) — pas de redémarrage." >> $LOG
# fi
