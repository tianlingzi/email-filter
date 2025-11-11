<?php
/**
 * EmailFilter - Typecho邮箱过滤插件
 * 
 * 过滤用户注册时使用的特定邮箱地址
 * 
 * @package EmailFilter
 * @author tianlingzi
 * @version 1.0.0
 * @link https://www.tianlingzi.top
 */

// PHP 8.4 兼容性增强：添加严格类型声明
declare(strict_types=1);

class EmailFilter_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return string
     * @throws Typecho_Plugin_Exception
     */
    public static function activate(): string
    {
        try {
            // 检查Typecho版本兼容性
            if (version_compare((string)Typecho_Common::VERSION, '1.2.1', '<')) {
                throw new Typecho_Plugin_Exception(_t('当前Typecho版本过低，建议升级到1.2.1或更高版本以获得最佳体验'));
            }
            
            // 检查PHP版本兼容性
            if (version_compare(PHP_VERSION, '8.0.0', '<')) {
                throw new Typecho_Plugin_Exception(_t('当前PHP版本过低，建议升级到PHP 8.0.0或更高版本以获得最佳体验'));
            }
            
            // 注册用户注册前的钩子
            Typecho_Plugin::factory('Widget_Register')->register = array('EmailFilter_Plugin', 'filterEmail');
            
            // 创建插件配置
            self::initConfig();
            
            // 记录激活日志
            self::log('插件激活成功');
            
            return _t('邮箱过滤插件已成功激活<br/>请在插件设置中配置需要过滤的邮箱地址');
        } catch (Typecho_Plugin_Exception $e) {
            self::log('插件激活失败: ' . $e->getMessage(), 'error');
            throw $e;
        } catch (\Exception $e) {
            self::log('插件激活时发生未预期错误: ' . $e->getMessage(), 'error');
            throw new Typecho_Plugin_Exception(_t('插件激活失败: %s', $e->getMessage()));
        }
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @access public
     * @return string
     */
    public static function deactivate(): string
    {
        try {
            // 记录禁用日志
            self::log('插件禁用成功');
            return _t('邮箱过滤插件已成功禁用');
        } catch (\Exception $e) {
            self::log('插件禁用失败: ' . $e->getMessage(), 'error');
            return _t('插件禁用过程中出现错误: %s', $e->getMessage());
        }
    }
    
    /**
     * 记录插件日志
     * 
     * @access private
     * @param string $message 日志消息
     * @param string $level 日志级别 (info, error, warning)
     * @return void
     */
    private static function log(string $message, string $level = 'info'): void
    {
        try {
            // 在开发环境中，可以取消注释下面的代码来启用日志记录
            /*
            // Nginx 兼容性增强：确保使用系统分隔符和安全路径处理
            $logDir = self::normalizePath(__TYPECHO_ROOT_DIR__ . DIRECTORY_SEPARATOR . 'usr' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'EmailFilter');
            $logFile = self::normalizePath($logDir . DIRECTORY_SEPARATOR . 'log.txt');
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] [$level] $message\n";
            
            // 确保日志目录存在
            if (!is_dir($logDir)) {
                // 安全创建目录，适用于各种服务器环境
                mkdir($logDir, 0755, true);
                // 设置正确的权限
                chmod($logDir, 0755);
            }
            
            // 写入日志
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            // 确保日志文件权限正确
            chmod($logFile, 0644);
            */
        } catch (\Exception $e) {
            // 静默处理日志错误，不影响插件正常运行
        }
    }
    
    /**
     * 规范化文件路径，确保跨平台和Nginx兼容性
     * 
     * @access private
     * @param string $path 原始路径
     * @return string 规范化后的路径
     */
    private static function normalizePath(string $path): string
    {
        // 替换所有类型的路径分隔符为系统分隔符
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        
        // 解决Nginx环境下可能出现的路径问题
        // 移除多余的分隔符
        while (strpos($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) !== false) {
            $path = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
        }
        
        return $path;
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form): void
    {
        // 添加插件介绍说明
        $intro = new Typecho_Widget_Helper_Form_Element_Textarea('intro', NULL, '', _t('插件介绍'), _t('EmailFilter 插件可以帮助您过滤用户注册时使用的特定邮箱地址。'), 'element-intro');
        $intro->setAttribute('readonly', true);
        $intro->setAttribute('style', 'background-color:#f5f5f5; color:#666; border:none; resize:none;');
        $form->addInput($intro);
        
        // 过滤邮箱列表设置项
        $filterEmails = new Typecho_Widget_Helper_Form_Element_Textarea('filterEmails', NULL, '', 
            _t('过滤邮箱列表'), 
            _t('请输入需要过滤的邮箱地址，每行一个。支持两种匹配模式：<br/>') . 
            _t('1. 精确匹配 - 例如：user@example.com<br/>') .
            _t('2. 通配符匹配 - 例如：*@example.com（匹配example.com域名下的所有邮箱）'));
        $filterEmails->setAttribute('rows', '10');
        $filterEmails->setAttribute('placeholder', '1245@test.com
*@test.com
spam@example.org');
        $form->addInput($filterEmails);
        
        // 通用域名过滤提示信息设置项
        $domainErrorMessage = new Typecho_Widget_Helper_Form_Element_Text('domainErrorMessage', NULL, '您所使用的邮箱域名未通过安全校验，请更换其他邮箱域名', 
            _t('通用域名过滤提示'), _t('当用户使用被过滤的通用域名时显示的提示信息（如：*@test.com）'));
        $domainErrorMessage->setAttribute('style', 'width: 100%; max-width: 500px;');
        $form->addInput($domainErrorMessage);
        
        // 特定邮箱过滤提示信息设置项
        $emailErrorMessage = new Typecho_Widget_Helper_Form_Element_Text('emailErrorMessage', NULL, '您所使用的邮箱已被禁止，请更换其他邮箱地址', 
            _t('特定邮箱过滤提示'), _t('当用户使用被过滤的特定邮箱地址时显示的提示信息'));
        $emailErrorMessage->setAttribute('style', 'width: 100%; max-width: 500px;');
        $form->addInput($emailErrorMessage);
        
        // 添加保存按钮样式调整 - 安全检查，避免null对象错误
        $submitInput = $form->getInput('submit');
        if ($submitInput !== null) {
            $submitInput->setAttribute('class', 'btn primary');
        }
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 初始化插件配置
     * 
     * @access private
     * @return void
     */
    private static function initConfig(): void
    {
        try {
            $optionsWidget = Typecho_Widget::widget('Widget_Options');
            $config = $optionsWidget->plugin('EmailFilter');
            
            // 如果没有配置，设置默认值 - 确保在Nginx环境下配置正确保存
            if (empty($config->filterEmails)) {
                $optionsWidget->config->setPlugin('EmailFilter', array(
                    'filterEmails' => '',
                    'errorMessage' => '该邮箱未在信任范围内，请更换其他邮箱'
                ));
                
                // 确保配置被正确保存，特别是在Nginx环境下
                $optionsWidget->pluginSave();
            }
        } catch (\Exception $e) {
            // 记录错误但不阻止插件激活
            self::log('初始化配置时发生错误: ' . $e->getMessage(), 'warning');
        }
    }
    
    /**
     * 过滤邮箱地址
     * 
     * @access public
     * @param array $data 注册用户数据
     * @param Typecho_Widget_Helper_Form|null $form 表单对象（可能为null）
     * @return array 处理后的数据
     * @throws Typecho_Widget_Exception
     */
    public static function filterEmail(array $data, $form = null): array
    {
        try {
            // 确保$form参数非空再使用
            if (!($form instanceof Typecho_Widget_Helper_Form) && $form !== null) {
                $form = null;
            }
            
            // 获取用户提交的邮箱 - PHP 8.4 兼容性：使用更安全的数组访问方式
            $email = $data['mail'] ?? '';
            
            // 确保邮箱是字符串类型
            if (!is_string($email)) {
                $email = '';
            }
            
            if (empty($email)) {
                return $data; // 邮箱为空时，让Typecho自己处理验证
            }
            
            // 获取插件配置
            $config = Typecho_Widget::widget('Widget_Options')->plugin('EmailFilter');
            
            // PHP 8.4 兼容性：安全地获取配置值
            $filterEmails = is_string($config->filterEmails) ? trim($config->filterEmails) : '';
            
            // 从配置中获取错误提示信息，提供默认值确保向后兼容性
            $domainErrorMessage = is_string($config->domainErrorMessage) && !empty($config->domainErrorMessage)
                ? $config->domainErrorMessage
                : '您所使用的邮箱域名未通过安全校验，请更换其他邮箱域名';
            
            $emailErrorMessage = is_string($config->emailErrorMessage) && !empty($config->emailErrorMessage)
                ? $config->emailErrorMessage
                : '您所使用的邮箱已被禁止，请更换其他邮箱地址';
            
            // 保留旧配置的兼容性支持
            $errorMessage = is_string($config->errorMessage) && !empty($config->errorMessage)
                ? $config->errorMessage
                : '该邮箱未在信任范围内，请更换其他邮箱';
            
            // 如果没有设置过滤规则，直接返回
            if (empty($filterEmails)) {
                return $data;
            }
            
            // 分割过滤列表，处理每行一个的情况
            $filterList = array_map('trim', explode("\n", $filterEmails));
            $filterList = array_filter($filterList); // 移除空行
            
            // 检查邮箱是否在过滤列表中
            foreach ($filterList as $filterPattern) {
                if (is_string($filterPattern) && self::isEmailMatch($email, $filterPattern)) {
                    // 根据过滤规则类型提供不同的错误提示
                    // 通用域名匹配（如 *@test.com）
                    if (strpos($filterPattern, '*@') === 0) {
                        throw new Typecho_Widget_Exception(_t($domainErrorMessage));
                    } else {
                        // 特定邮箱匹配（如 123@tesst.com）
                        throw new Typecho_Widget_Exception(_t($emailErrorMessage));
                    }
                }
            }
            
            // 邮箱未被过滤，继续注册流程
            return $data;
        } catch (Typecho_Widget_Exception $e) {
            // 重新抛出Widget异常
            throw $e;
        } catch (\Exception $e) {
            // 记录错误但不阻止注册流程，避免因插件错误影响正常功能
            self::log('邮箱过滤过程中发生错误: ' . $e->getMessage(), 'error');
            return $data;
        }
    }
    
    /**
     * 检查邮箱是否匹配过滤规则
     * 
     * @access private
     * @param string $email 用户邮箱
     * @param string $pattern 过滤规则
     * @return bool 是否匹配
     */
    private static function isEmailMatch(string $email, string $pattern): bool
    {
        // 精确匹配
        if ($pattern === $email) {
            return true;
        }
        
        // 处理通配符匹配，如 *@example.com
        if (strpos($pattern, '*') !== false) {
            try {
                // PHP 8.4 兼容性：使用更安全的正则表达式构建方式
                // 先转义特殊字符，然后将 * 替换为 .*
                $patternEscaped = preg_quote($pattern, '/');
                $regexPattern = '/^' . str_replace('\\*', '.*', $patternEscaped) . '$/i';
                
                // 确保正则表达式有效
                if (@preg_match($regexPattern, '') === false) {
                    // 正则表达式无效，记录错误并返回false
                    self::log('无效的正则表达式模式: ' . $pattern, 'error');
                    return false;
                }
                
                return preg_match($regexPattern, $email) === 1;
            } catch (\ErrorException $e) {
                // 捕获可能的正则表达式错误
                self::log('正则表达式处理错误: ' . $e->getMessage(), 'error');
                return false;
            }
        }
        
        return false;
    }
}