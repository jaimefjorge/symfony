<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath\Extension;

use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator function extension.
 *
 * This component is a port of the Python cssselector library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class FunctionExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctionTranslators()
    {
        return array(
            'nth-child'        => array($this, 'translateNthChild'),
            'nth-last-child'   => array($this, 'translateNthLastChild'),
            'nth-of-type'      => array($this, 'translateNthOfType'),
            'nth-last-of-type' => array($this, 'translateNthLastOfType'),
            'contains'         => array($this, 'translateContains'),
            'lang'             => array($this, 'translateLang'),
        );
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     * @param boolean      $last
     * @param boolean      $addNameTest
     *
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateNthChild(XPathExpr $xpath, FunctionNode $function, $last = false, $addNameTest = true)
    {
        try {
            list($a, $b) = Parser::parseSeries($function->getArguments());
        } catch (SyntaxErrorException $e) {
            throw new ExpressionErrorException('Invalid series: '.implode(', ', $function->getArguments()), 0, $e);
        }

        $xpath->addStarPrefix();
        if ($addNameTest) {
            $xpath->addNameTest();
        }

        if (0 === $a) {
            return $xpath->addCondition('position() = '.($last ? 'last() - '.$b : $b));
        }

        if ($last) {
            // todo: verify if this is right
            $a = - $a;
            $b = - $b;
        }

        $conditions = 1 === $a
            ? array()
            : array(sprintf('(position() %s) mod %s = 0', $b > 0 ? (string) (- $b) : '+'.(- $b), $a));

        if ($b >= 0) {
            $conditions[] = 'position() >= '.$b;
        } elseif ($last) {
            $conditions[] = sprintf('position() < (last() %s)', $b);
        }

        // todo: handle an+b, odd, even
        // an+b means every-a, plus b, e.g., 2n+1 means odd
        // 0n+b means b
        // n+0 means a=1, i.e., all elements
        // an means every a elements, i.e., 2n means even
        // -n means -1n
        // -1n+6 means elements 6 and previous

        return empty($conditions) ? $xpath : $xpath->addCondition(implode(' and ', $conditions));
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     */
    public function translateNthLastChild(XPathExpr $xpath, FunctionNode $function)
    {
        return $this->translateNthChild($xpath, $function, true);
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     */
    public function translateNthOfType(XPathExpr $xpath, FunctionNode $function)
    {
        return $this->translateNthChild($xpath, $function, false, false);
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateNthLastOfType(XPathExpr $xpath, FunctionNode $function)
    {
        if ('*' === $xpath->getElement()) {
            throw new ExpressionErrorException('"*:nth-of-type()" is not implemented.');
        }

        return $this->translateNthChild($xpath, $function, true, false);
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateContains(XPathExpr $xpath, FunctionNode $function)
    {
        $arguments = $function->getArguments();
        foreach ($arguments as $token) {
            if (!($token->isString() || $token->isIdentifier())) {
                throw new ExpressionErrorException(
                    'Expected a single string or identifier for :contains(), got '
                    .implode(', ', $arguments)
                );
            }
        }

        return $xpath->addCondition(sprintf(
            'contains(string(.), %s)',
            Translator::getXpathLiteral($arguments[0]->getValue())
        ));
    }

    /**
     * @param XPathExpr    $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateLang(XPathExpr $xpath, FunctionNode $function)
    {
        $arguments = $function->getArguments();
        foreach ($arguments as $token) {
            if (!($token->isString() || $token->isIdentifier())) {
                throw new ExpressionErrorException(
                    'Expected a single string or identifier for :lang(), got '
                    .implode(', ', $arguments)
                );
            }
        }

        return $xpath->addCondition(sprintf(
            'lang(%s)',
            Translator::getXpathLiteral($arguments[0]->getValue())
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'function';
    }
}
