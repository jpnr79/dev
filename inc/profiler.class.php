<?php

use Mexitek\PHPColors\Color;

class PluginDevProfiler extends CommonGLPI {

   /** @var DateTime */
   private static $session_start;

   /** @var string */
   private static $session_uuid;

   /** @var PluginDevProfilerSection[] */
   private static $prev_sections = [];

   /** @var PluginDevProfilerSection[] */
   private static $current_sections = [];

   public static $disabled = false;

   public static function getTypeName($nb = 0)
   {
      return _x('tool', 'Profiler', 'dev');
   }

   public static function start(string $name, string $category = 'core'): void {
      if (self::$disabled) {
         return;
      }
      if (array_key_exists($name, self::$current_sections)) {
         \Toolbox::logWarning("Profiler section $name already started");
         return;
      }
      if (self::$session_start === null) {
         self::$session_start = new \DateTime('now');
         self::$session_uuid = session_id() ?? (string) \Ramsey\Uuid\Uuid::uuid4();//uniqid('', true);
      }
      self::$current_sections[$name] = new PluginDevProfilerSection($category, microtime(true) * 1000);
      self::logProfilerSession("[$category]\tStarted section $name");
   }

   public static function end(string $name): void {
      if (!array_key_exists($name, self::$current_sections)) {
         \Toolbox::logWarning("Profiler section $name has not been started");
         return;
      }
      self::$current_sections[$name]->end(microtime(true) * 1000);
      self::$prev_sections[$name] = self::$current_sections[$name];
      unset(self::$current_sections[$name]);
      $category = self::$prev_sections[$name]->getCategory();
      $duration = self::$prev_sections[$name]->getDuration();
      self::logProfilerSession("[$category]\tEnded section $name after $duration ms");
   }

   private static function getProfilerLogDir(): string {
      return GLPI_ROOT.'/'.\Plugin::getWebDir('dev', false).'/var/profiler_sessions/';
   }

   private static function getProfilerLogPath(): string {
      //return GLPI_ROOT.'/'.\Plugin::getWebDir('dev', false).'/var/profiler_sessions/'.self::$session_start->format('Y-m-d H-i-s-u').'.log';
      return self::getProfilerLogDir().self::$session_start->format('Y-m-d').'.log';
   }

   private static function logProfilerSession($message): void {
      $log_path = self::getProfilerLogPath();
      $ts = (new \DateTime('now'))->format('Y-m-d H:i:s:u');
      $session_uuid = self::$session_uuid;
      $log = fopen($log_path, 'ab');
      fwrite($log, "[$ts]\t[$session_uuid]\t".$message . "\n");
      fclose($log);
   }

   private static function getProfilerSessionFiles(): array {
      $files = glob(self::getProfilerLogDir().'*.log');
      $log_files = [];

      foreach ($files as $file) {
         $log_files[] = [
            'name'   => explode('.', basename($file))[0],
            'file'   => $file
         ];
      }

      return $log_files;
   }

   private static function getSessions(string $file): array {
      $sessions = [];
      $log = fopen($file, 'rb');
      while (($line = fgets($log)) !== false) {
         $tokens = explode("\t", $line);
         $ts = null;
         preg_match('/\[(.*)\]/', $tokens[0], $ts);
         $ts = $ts[1];
         $session_id = preg_match('/\[(.*)\]/', $tokens[1]);
         preg_match('/\[(.*)\]/', $tokens[1], $session_id);
         $session_id = $session_id[1];
         $category = preg_match('/\[(.*)\]/', $tokens[2]);
         preg_match('/\[(.*)\]/', $tokens[2], $category);
         $category = $category[1];
         $message = $tokens[3];

         if (!isset($sessions[$session_id])) {
            $sessions[$session_id] = [];
         }

         $event = [
            'timestamp' => $ts,
            'category'  => $category,
            'message'   => $message,
         ];
         if (str_starts_with($message, 'Started section')) {
            $event['name'] = trim(preg_replace('/^Started section/', '', $message));
            $event['start'] = $ts;
            $sessions[$session_id][] = $event;
         } else if (str_starts_with($message, 'Ended section')) {
            $sub_tokens = explode('after', trim(preg_replace('/^Ended section/', '', $message)));
            $name = trim($sub_tokens[0]);
            $duration = trim(str_replace('/ ms$/', '', $sub_tokens[1]));
            // Find matching existing event
            foreach ($sessions as $session_id => &$events) {
               $is_matched = false;
               foreach ($events as &$event) {
                  if (!isset($event['end']) && isset($event['name']) && $event['name'] === $name) {
                     $event['end'] = $ts;
                     $event['duration'] = $duration;
                     $is_matched = true;
                     break;
                  }
               }
               unset($event);
               if ($is_matched) {
                  break;
               }
            }
         }
      }
      return $sessions;
   }

   public static function showDashboard(string $selected_log = null, string $selected_session = null): void {
      $output = '<div id="devprofiler-container">';
      $log_select_label = __('Profiler log', 'dev');
      $output .= "<label id='devprofiler-logselect-label'>{$log_select_label}</label>";
      $output .= '<select class="ms-1" aria-labelledby="devprofiler-logselect-label">';
      $logs = self::getProfilerSessionFiles();
      if (count($logs) > 0) {
         if ($selected_log === null) {
            $selected_log = end($logs)['file'];
         } else {
            $selected_log = self::getProfilerLogDir().$selected_log.'.log';
         }
         foreach ($logs as $log) {
            if ($log['file'] === $selected_log) {
               $output .= "<option value='{$log['name']}' selected='selected'>{$log['name']}</option>";
            } else {
               $output .= "<option value='{$log['name']}'>{$log['name']}</option>";
            }
         }
      }
      $output .= '</select>';

      if ($selected_log === null) {
         return;
      }
      $sessions = self::getSessions($selected_log);

      if ($selected_session === null) {
         $selected_session = array_key_last($sessions);
      }
      $output .= "<select>";
      foreach ($sessions as $session_id => $events) {
         if ($session_id === $selected_session) {
            $output .= "<option value='{$session_id}' selected='selected'>{$session_id}</option>";
         } else {
            $output .= "<option value='{$session_id}'>{$session_id}</option>";
         }
      }
      $output .= "</select>";

      $output .= <<<HTML
<table class="table table-striped card-table table-hover">
    <thead>
        <tr>
           <th>Category</th>
           <th>Name</th>
           <th>Start</th>
           <th>End</th>
           <th>Duration</th>
       </tr>
    </thead>
    <tbody>
HTML;

      // Store colors to avoid re-calculation during the same request. Predefined some colors.
      $category_colors = [
         'core'   => new Color('526dad'),
         'db'     => new Color('9252ad'),
         'twig'   => new Color('64ad52'),
      ];
      $calc_color = static function($str) {
         $code = dechex(crc32($str));
         $code = substr($code, 0, 6);
         try {
            return new Color($code);
         } catch (Exception $e) {
            return substr(dechex(mt_rand()), 0, 6);
         }
      };

      foreach ($sessions as $session_id => $events) {
         if ($session_id !== $selected_session) {
            continue;
         }
         foreach ($events as $event) {
            $category = $event['category'];
            $name = $event['name'];
            $start = $event['start'];
            $end = $event['end'] ?? '';
            $duration = $event['duration'] ?? '';

            // Calculate color if needed
            if (!isset($category_colors[$category])) {
               $category_colors[$category] = $calc_color($category);
            }

            $bg_color = '#'.$category_colors[$category]->getHex();
            $fg_color = $category_colors[$category]->isLight() ? 'var(--dark)' : 'var(--light)';
            $output .= "<tr>
                <td><span style='padding: 5px; border-radius: 25%; background-color: {$bg_color}; color: {$fg_color}'>{$category}</span></td>
                <td>{$name}</td><td>{$start}</td><td>{$end}</td><td>{$duration}</td>
            </tr>";
         }
      }
      $output .= '</tbody></table>';
      $output .= '</div>';

      echo $output;
   }
}
